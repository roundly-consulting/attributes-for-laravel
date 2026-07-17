<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Tests\Fixtures\SwappedAttributeTestCase;
use RoundlyConsulting\Attributes\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different
// base case, and a blanket bind would claim it first. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the `attributes.model` config default and so needs
// the app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in('ArchTest.php', 'AttributesTest.php', 'Feature', 'Unit');

// The model-swap proofs need `attributes.model` pointed at the host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedAttributeTestCase::class)->in('ModelSwap');
