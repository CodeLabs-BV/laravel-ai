<?php

namespace CodeLabs\Ai\Tests;

use CodeLabs\Ai\CodeLabsAiServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AiServiceProvider::class, CodeLabsAiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('codelabs-ai.connections.codelabs.key', 'sk-test');
        $app['config']->set('codelabs-ai.connections.codelabs.max_tokens', 256);
    }
}
