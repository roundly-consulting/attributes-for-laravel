<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Attributes\Commands\ListAttributesCommand;
use RoundlyConsulting\Attributes\Commands\PruneAttributesCommand;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

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

            $registry->define($this->makeDefinition((string) $name, $definition));
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function makeDefinition(string $name, array $definition): AttributeDefinitionData
    {
        $type = $definition['type'] ?? AttributeType::String_->value;

        /** @var list<string|object> $rules */
        $rules = is_array($definition['rules'] ?? null) ? array_values($definition['rules']) : [];

        return new AttributeDefinitionData(
            name: $name,
            type: is_string($type) ? AttributeType::tryFrom($type) ?? AttributeType::String_ : AttributeType::String_,
            rules: $rules,
            default: $definition['default'] ?? null,
            required: (bool) ($definition['required'] ?? false),
        );
    }
}
