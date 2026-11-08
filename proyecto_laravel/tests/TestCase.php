<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=keydeploy_test');
        putenv('DB_URL=');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_DATABASE'] = 'keydeploy_test';
        $_ENV['DB_URL'] = '';
        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_DATABASE'] = 'keydeploy_test';
        $_SERVER['DB_URL'] = '';

        return parent::createApplication();
    }
}
