<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use RoundlyConsulting\Attributes\Commands\ListAttributesCommand;
use RoundlyConsulting\Attributes\Commands\PruneAttributesCommand;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class AttributesServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('attributes')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                ListAttributesCommand::class,
                PruneAttributesCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(AttributeModel::class()),
                'Table' => self::table(),
                'Strict mode' => config('attributes.strict', false) === true ? 'ON' : 'OFF',
                'History' => config('attributes.history.enabled', false) === true ? 'ON' : 'OFF',
                'Prune after' => self::pruneAfterDays().' day(s)',
                // Definitions are reported by count only: an attribute name is a
                // host's field name (api_token, ssn, …) and often names the very
                // secret the encrypted flag protects.
                'Definitions' => self::definitions(),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AttributeRegistry::class);
        $this->app->singleton(AttributesManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        $this->hydrateRegistry();
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

    private static function table(): string
    {
        $table = config('attributes.table', 'attributes');

        return is_string($table) && $table !== '' ? $table : 'attributes';
    }

    private static function pruneAfterDays(): string
    {
        $days = config('attributes.prune_after_days', 30);

        return (string) (is_numeric($days) ? (int) $days : 30);
    }

    private static function definitions(): string
    {
        $definitions = config('attributes.definitions', []);

        if (! is_array($definitions) || $definitions === []) {
            return 'FREE-FORM';
        }

        $encrypted = 0;

        foreach ($definitions as $definition) {
            if (is_array($definition) && ($definition['encrypted'] ?? false) === true) {
                $encrypted++;
            }
        }

        return count($definitions).' defined ('.$encrypted.' encrypted)';
    }
}
