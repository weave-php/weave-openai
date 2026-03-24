<?php

namespace Weave\OpenAI\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Weave\Core\AutomationRegistry;
use Weave\OpenAI\OpenAIServiceProvider;
use Weave\Triggers\ScheduleRegistry;
use Weave\WeaveServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WeaveServiceProvider::class,
            OpenAIServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('weave.logging.driver', 'null');
        $app['config']->set('weave.execution.driver', 'sync');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        AutomationRegistry::flush();
        ScheduleRegistry::flush();

        OpenAIServiceProvider::registerAutomationProviders();
    }
}
