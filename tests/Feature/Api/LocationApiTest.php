<?php

namespace Tests\Feature\Api;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de la API pública: formato {status, data} y 404 {status:"error", message}.
 * Corre contra MySQL/MariaDB (escalebridge_test) porque los endpoints usan LEFT().
 */
class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_regiones_devuelve_todas_las_regiones_con_formato_success(): void
    {
        Region::factory()->create([
            'idRegion' => '15',
            'nombreRegion' => 'LIMA',
            'pointX' => -12.046374,
            'pointY' => -77.042793,
            'zoom' => 10,
        ]);
        Region::factory()->create(['idRegion' => '04']);

        $response = $this->getJson('/api/regiones');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [['idRegion', 'nombreRegion', 'pointX', 'pointY', 'zoom', 'created_at', 'updated_at']],
            ]);

        $lima = collect($response->json('data'))->firstWhere('idRegion', '15');
        $this->assertSame('LIMA', $lima['nombreRegion']);
        // Tipos observados en PHP 8.3 + pdo_mysql: DECIMAL como string, INT como int.
        $this->assertSame('-12.0463740', $lima['pointX']);
        $this->assertSame('-77.0427930', $lima['pointY']);
        $this->assertSame(10, $lima['zoom']);
    }

    public function test_regiones_sin_datos_devuelve_200_con_lista_vacia(): void
    {
        $this->getJson('/api/regiones')
            ->assertOk()
            ->assertExactJson(['status' => 'success', 'data' => []]);
    }

    public function test_provincias_filtra_por_los_dos_primeros_digitos(): void
    {
        Province::factory()->create(['idProvincia' => '1501', 'nombreProvincia' => 'LIMA']);
        Province::factory()->create(['idProvincia' => '1502']);
        Province::factory()->create(['idProvincia' => '1401']);

        $response = $this->getJson('/api/provincias/15');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['status', 'data' => [['idProvincia', 'nombreProvincia', 'pointX', 'pointY']]]);

        $this->assertEqualsCanonicalizing(
            ['1501', '1502'],
            array_column($response->json('data'), 'idProvincia')
        );
    }

    public function test_provincias_sin_coincidencias_devuelve_404(): void
    {
        Province::factory()->create(['idProvincia' => '1501']);

        $this->getJson('/api/provincias/99')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron provincias para esta región.',
            ]);
    }

    public function test_provincias_compara_exactamente_dos_caracteres(): void
    {
        Province::factory()->create(['idProvincia' => '1501']);

        // LEFT(idProvincia, 2) = '1' nunca coincide: no es un "empieza por".
        $this->getJson('/api/provincias/1')->assertNotFound();
    }

    public function test_distritos_filtra_por_los_cuatro_primeros_digitos(): void
    {
        District::factory()->create(['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']);
        District::factory()->create(['idDistrito' => '150102', 'nombreDistrito' => 'ANCON']);
        District::factory()->create(['idDistrito' => '150201']);

        $response = $this->getJson('/api/distritos/1501');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['status', 'data' => [['idDistrito', 'nombreDistrito']]]);

        $this->assertEqualsCanonicalizing(
            ['150101', '150102'],
            array_column($response->json('data'), 'idDistrito')
        );
    }

    public function test_distritos_sin_coincidencias_devuelve_404(): void
    {
        $this->getJson('/api/distritos/9999')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron distritos para esta provincia.',
            ]);
    }

    public function test_colegios_filtra_por_ubigeo_completo(): void
    {
        School::factory()->create([
            'codMod' => '0273572',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
            'cenPob' => 'LIMA',
            'dirCen' => 'JR. LAMPA 240',
            'director' => 'PEDRO PEREZ',
        ]);
        School::factory()->create(['idDistrito' => '150101']);
        School::factory()->create(['idDistrito' => '150102']);

        $response = $this->getJson('/api/colegios/150101');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['status', 'data' => [[
                'codMod', 'idDistrito', 'nombreCenEdu', 'cenPob', 'dirCen', 'director', 'dis_crecer', 'dis_juntos',
            ]]]);

        $colegio = collect($response->json('data'))->firstWhere('codMod', '0273572');
        $this->assertSame('JUAN JIMENEZ PIMENTEL', $colegio['nombreCenEdu']);
        $this->assertNull($colegio['dis_crecer']);
    }

    public function test_colegios_sin_coincidencias_devuelve_404(): void
    {
        $this->getJson('/api/colegios/000000')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron escuelas para este distrito.',
            ]);
    }

    public function test_api_conserva_throttle_60_por_minuto_y_cors_abierto(): void
    {
        $this->getJson('/api/regiones', ['Origin' => 'http://cliente.test'])
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_ruta_user_eliminada_no_existe(): void
    {
        $this->getJson('/api/user')->assertNotFound();
    }
}
