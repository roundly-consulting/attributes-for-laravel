<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use RoundlyConsulting\Attributes\Commands\ListAttributesCommand;
use RoundlyConsulting\Attributes\Commands\PruneAttributesCommand;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributesConfig;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
                'Strict mode' => Config::boolean('attributes.strict') ? 'ON' : 'OFF',
                'History' => Config::boolean('attributes.history.enabled') ? 'ON' : 'OFF',
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
        $definitions = config('attributes.definitions') ?? [];

        if (! is_array($definitions)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [attributes.definitions] must be an array of definitions, [%s] given.',
                get_debug_type($definitions),
            ));
        }

        if ($definitions === []) {
            return;
        }

        $registry = $this->app->make(AttributeRegistry::class);

        foreach ($definitions as $name => $definition) {
            $registry->define(DefinitionFactory::fromRaw((string) $name, $definition));
        }
    }

    private static function table(): string
    {
        return AttributesConfig::table();
    }

    private static function pruneAfterDays(): string
    {
        return (string) AttributesConfig::pruneAfterDays();
    }

    private static function definitions(): string
    {
        $definitions = config('attributes.definitions', []);

        if (! is_array($definitions) || $definitions === []) {
            return 'FREE-FORM';
        }

        // Parsed the way the registry parses them, so an `'encrypted' => 'on'` counts here
        // too, and a broken definition fails `about` as it fails boot.
        $encrypted = 0;

        foreach ($definitions as $name => $definition) {
            if (DefinitionFactory::fromRaw((string) $name, $definition)->encrypted) {
                $encrypted++;
            }
        }

        return count($definitions).' defined ('.$encrypted.' encrypted)';
    }
}
