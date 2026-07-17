<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Tests\Fixtures\SwappedRequestTestCase;
use RoundlyConsulting\Requests\Tests\TestCase;

// Explicit paths, not the previous blanket `->in(__DIR__)`: the ModelSwap directory below
// needs a different base case, and a blanket bind would claim it first (Pest binds a test
// case per directory, not per file, and errors loudly on a blanket-vs-specific collision).
//
// ArchTest.php is listed by FILE path — `uses()->in()` accepts one — because
// `swappableModelsAreNotFinal` reads the `requests.model` config default and so needs the
// app booted. An arch file is not automatically test-cased: passkeys' ArchTest was bound to
// nothing at all and its finality preset could never read a config default.
uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proof needs `requests.model` pointed at the host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory.
uses(SwappedRequestTestCase::class)->in('ModelSwap');
