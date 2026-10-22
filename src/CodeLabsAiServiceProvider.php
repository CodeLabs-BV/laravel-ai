<?php

namespace CodeLabs\Ai;

use CodeLabs\Ai\Console\KeyCommand;
use CodeLabs\Ai\Console\PingCommand;
use CodeLabs\Ai\Console\UsageCommand;
use CodeLabs\Ai\Contracts\KeyStore;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

class CodeLabsAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/codelabs-ai.php', 'codelabs-ai');

        $this->app->singleton(KeyStore::class, fn ($app) => ($store = $app['config']->get('codelabs-ai.key_store'))
            ? $app->make($store)
            : null);

        $this->app->singleton(CodeLabsAi::class, fn ($app) => new CodeLabsAi(
            $app['config'],
            $app->make(Factory::class),
            $app,
            $app->make(KeyStore::class),
        ));

        $this->callAfterResolving(AiManager::class, function (AiManager $manager): void {
            $manager->extend(CodeLabsProvider::DRIVER, fn ($app, array $config) => new CodeLabsProvider(
                $config,
                $app->make(Dispatcher::class),
            ));
        });
    }

    public function boot(): void
    {
        // In boot rather than register, so config other providers set while
        // registering (or a test's environment) is already in place.
        $this->app->make(CodeLabsAi::class)->registerProviders();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/codelabs-ai.php' => config_path('codelabs-ai.php'),
            ], 'codelabs-ai-config');

            $this->commands([UsageCommand::class, PingCommand::class, KeyCommand::class]);
        }
    }
}
