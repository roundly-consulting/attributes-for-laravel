<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Attributes\Commands\ListAttributesCommand;
use RoundlyConsulting\Attributes\Commands\PruneAttributesCommand;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;

final class AttributesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/attributes.php', 'attributes');

        $this->app->singleton(AttributeRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->hydrateRegistry();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListAttributesCommand::class,
                PruneAttributesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/attributes.php' => config_path('attributes.php'),
            ], 'attributes-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'attributes-migrations');
        }
    }

    private function hydrateRegistry(): void
    {
        $definitions = config('attributes.definitions', []);

        if (! is_array($definitions) || $definitions === []) {
            return;
        }

        $registry = $this->app->make(AttributeRegistry::class);

        foreach ($definitions as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $registry->define(DefinitionFactory::fromArray((string) $name, $definition));
        }
    }
}
