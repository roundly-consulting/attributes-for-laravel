<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Fixtures;

use RoundlyConsulting\Attributes\Tests\Models\CustomAttribute;
use RoundlyConsulting\Attributes\Tests\TestCase;

/**
 * The suite's base case with `attributes.model` already pointed at
 * {@see CustomAttribute} BEFORE the providers boot.
 *
 * Boot order is the whole point. The provider hangs its registry hydration and every
 * observer on whatever `attributes.model` names at boot; a `config()->set()` inside a test
 * body reads back correctly and leaves all of that on the packaged Attribute. That is the
 * shape the tests this replaces had — `Unit/Support/AttributeModelTest.php` set the config
 * at runtime and asserted `instanceof`, and neither half could catch a row created as the
 * packaged class (which fires none of the host's model events).
 *
 * The `array_merge(parent::configBeforeBoot(), …)` is not decoration: dropping it silently
 * discards whatever the base case wires, with no error and no red — the same decapitation
 * an un-parented `defineEnvironment()` override causes one level up. It is empty today;
 * that is not a reason to omit it.
 *
 * @see TestCase
 */
abstract class SwappedAttributeTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'attributes.model' => CustomAttribute::class,
        ]);
    }
}
