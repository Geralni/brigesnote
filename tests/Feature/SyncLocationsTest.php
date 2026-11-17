<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * sync:locations con la API de ESCALE simulada. Ninguna prueba sale a internet:
 * preventStrayRequests() hace fallar cualquier petición no simulada.
 */
class SyncLocationsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://escale.minedu.gob.pe/padron/rest/';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Simula ESCALE con un mapa endpoint => (parámetros de la query => respuesta).
     *
     * @param  array<string, callable(array<string, string>): mixed>  $routes
     */
    private function fakeEscale(array $routes): void
    {
        Http::fake(function (Request $request) use ($routes) {
            $parts = parse_url($request->url());
            parse_str($parts['query'] ?? '', $query);
            $endpoint = basename($parts['path']);

            if (! isset($routes[$endpoint])) {
                return Http::response(['error' => "endpoint no simulado: {$endpoint}"], 599);
            }

            $result = $routes[$endpoint]($query);

            return $result instanceof \GuzzleHttp\Promise\PromiseInterface ? $result : Http::response($result);
        });
    }

    private function region(string $id, string $nombre): array
    {
        return ['idRegion' => $id, 'nombreRegion' => $nombre, 'pointX' => -12.046374, 'pointY' => -77.042793, 'zoom' => 10];
    }

    private function province(string $id, string $nombre): array
    {
        return ['idProvincia' => $id, 'nombreProvincia' => $nombre, 'pointX' => -12.05, 'pointY' => -77.04];
    }

    private function school(string $codMod, string $nombre): array
    {
        return [
            'codMod' => $codMod,
            'cenEdu' => $nombre,
            'cenPob' => 'LIMA',
            'dirCen' => 'JR. LAMPA 240',
            'director' => 'PEDRO PEREZ',
        ];
    }

    public function test_sincroniza_regiones_provincias_distritos_y_colegios_en_formato_lista(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('15', 'LIMA')]],
            'provincias' => fn () => ['provincia' => [
                $this->province('1501', 'LIMA'),
                $this->province('1502', 'BARRANCA'),
            ]],
            'distritos' => fn ($q) => ['distrito' => $q['idProvincia'] === '1501'
                ? [['idDistrito' => '150101', 'nombreDistrito' => 'LIMA'], ['idDistrito' => '150102', 'nombreDistrito' => 'ANCON']]
                : [['idDistrito' => '150201', 'nombreDistrito' => 'BARRANCA']]],
            'instituciones' => fn ($q) => ['items' => [
                $this->school('C'.$q['ubigeo'].'1', 'COLEGIO A '.$q['ubigeo']),
                $this->school('C'.$q['ubigeo'].'2', 'COLEGIO B '.$q['ubigeo']),
            ]],
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('Iniciando sincronización de ubicaciones...')
            ->expectsOutput('Sincronización completa.')
            ->assertExitCode(0);

        $this->assertSame(1, Region::count());
        $this->assertSame(2, Province::count());
        $this->assertSame(3, District::count());
        $this->assertSame(6, School::count());

        $this->assertDatabaseHas('regions', ['idRegion' => '15', 'nombreRegion' => 'LIMA', 'zoom' => 10]);
        $this->assertDatabaseHas('provinces', ['idProvincia' => '1502', 'nombreProvincia' => 'BARRANCA']);
        $this->assertDatabaseHas('districts', ['idDistrito' => '150102', 'nombreDistrito' => 'ANCON']);
        $this->assertDatabaseHas('schools', [
            'codMod' => 'C1501011',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'COLEGIO A 150101',
            'cenPob' => 'LIMA',
            'dirCen' => 'JR. LAMPA 240',
            'director' => 'PEDRO PEREZ',
            'dis_crecer' => null,
            'dis_juntos' => null,
        ]);
    }

    public function test_envia_los_parametros_esperados_a_escale(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('15', 'LIMA')]],
            'provincias' => fn () => ['provincia' => [$this->province('1501', 'LIMA')]],
            'distritos' => fn () => ['distrito' => [['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']]],
            'instituciones' => fn () => ['items' => [$this->school('0273572', 'JUAN JIMENEZ PIMENTEL')]],
        ]);

        $this->artisan('sync:locations')->assertExitCode(0);

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'regiones'
            && $r->hasHeader('Accept', 'application/json'));
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'provincias?idRegion=15');
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'distritos?idProvincia=1501');
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'instituciones?estados=1&ubigeo=150101');
    }

    public function test_acepta_respuestas_de_un_solo_objeto_en_vez_de_lista(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('07', 'CALLAO')]],
            'provincias' => fn () => ['provincia' => $this->province('0701', 'CALLAO')],
            'distritos' => fn () => ['distrito' => ['idDistrito' => '070101', 'nombreDistrito' => 'CALLAO']],
            'instituciones' => fn () => ['items' => [
                'codMod' => '0000001',
                'cenEdu' => 'UNICO COLEGIO',
                'dis_crecer' => 'SI',
            ]],
        ]);

        $this->artisan('sync:locations')->assertExitCode(0);

        $this->assertDatabaseHas('provinces', ['idProvincia' => '0701', 'nombreProvincia' => 'CALLAO']);
        $this->assertDatabaseHas('districts', ['idDistrito' => '070101']);
        $this->assertDatabaseHas('schools', [
            'codMod' => '0000001',
            'idDistrito' => '070101',
            'nombreCenEdu' => 'UNICO COLEGIO',
            'cenPob' => null,
            'dirCen' => null,
            'director' => null,
            'dis_crecer' => 'SI',
        ]);
    }

    public function test_distrito_sin_items_informa_y_continua(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('15', 'LIMA')]],
            'provincias' => fn () => ['provincia' => [$this->province('1501', 'LIMA')]],
            'distritos' => fn () => ['distrito' => [['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']]],
            'instituciones' => fn () => ['totalItems' => 0],
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('No se encontraron registros de escuelas')
            ->expectsOutput('Sincronización completa.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('districts', ['idDistrito' => '150101']);
        $this->assertSame(0, School::count());
    }

    public function test_error_http_en_provincias_informa_y_mantiene_exit_code_0(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('15', 'LIMA'), $this->region('04', 'AREQUIPA')]],
            'provincias' => fn ($q) => $q['idRegion'] === '15'
                ? Http::response('Error interno', 500)
                : ['provincia' => [$this->province('0401', 'AREQUIPA')]],
            'distritos' => fn () => ['distrito' => [['idDistrito' => '040101', 'nombreDistrito' => 'AREQUIPA']]],
            'instituciones' => fn () => ['items' => [$this->school('0400001', 'COLEGIO AREQUIPA')]],
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('Error al obtener provincias para la región 15')
            ->expectsOutput('Sincronización completa.')
            ->assertExitCode(0);

        $this->assertSame(2, Region::count());
        $this->assertDatabaseHas('provinces', ['idProvincia' => '0401']);
        $this->assertDatabaseMissing('provinces', ['idProvincia' => '1501']);
        $this->assertDatabaseHas('schools', ['codMod' => '0400001']);
    }

    public function test_error_http_en_regiones_no_guarda_nada_y_mantiene_exit_code_0(): void
    {
        $this->fakeEscale([
            'regiones' => fn () => Http::response('Servicio no disponible', 503),
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('Error al obtener regiones')
            ->expectsOutput('Sincronización completa.')
            ->assertExitCode(0);

        $this->assertSame(0, Region::count());
        Http::assertSentCount(1);
    }

    public function test_es_idempotente_y_actualiza_registros_existentes(): void
    {
        $nombre = 'COLEGIO ORIGINAL';
        $this->fakeEscale([
            'regiones' => fn () => ['region' => [$this->region('15', 'LIMA')]],
            'provincias' => fn () => ['provincia' => [$this->province('1501', 'LIMA')]],
            'distritos' => fn () => ['distrito' => [['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']]],
            'instituciones' => function () use (&$nombre) {
                return ['items' => [$this->school('0273572', $nombre)]];
            },
        ]);

        $this->artisan('sync:locations')->assertExitCode(0);
        $nombre = 'COLEGIO RENOMBRADO';
        $this->artisan('sync:locations')->assertExitCode(0);

        $this->assertSame(1, Region::count());
        $this->assertSame(1, Province::count());
        $this->assertSame(1, District::count());
        $this->assertSame(1, School::count());
        $this->assertDatabaseHas('schools', ['codMod' => '0273572', 'nombreCenEdu' => 'COLEGIO RENOMBRADO']);
    }
}
