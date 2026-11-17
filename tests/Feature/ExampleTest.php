<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La ruta "/" sigue respondiendo 200 con la vista welcome.
     */
    public function test_la_raiz_responde_200(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
