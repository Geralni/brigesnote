<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Salvaguarda: RefreshDatabase borra y recrea las tablas, así que las
     * pruebas solo pueden correr contra la base escalebridge_test.
     */
    protected function setUpTraits()
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if ($database !== 'escalebridge_test') {
            throw new RuntimeException("Las pruebas deben usar escalebridge_test, no [{$database}].");
        }

        return parent::setUpTraits();
    }
}
