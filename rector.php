<?php

declare(strict_types=1);

use Pest\Rector\Rules\SimplifyToLiteralBooleanRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/tests',
    ])
    // CODING_STYLE is the only set pest-plugin-rector ships. Its rules rewrite
    // raw PHPUnit assertions into Pest matchers, so they are meaningful only
    // against test files -- pointing it at app/ would be a no-op at best.
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    // This rule rewrites toBe('') into toBeEmpty(). SheetRow types the columns
    // it fills as string, so the rewrite widens the assertion to also accept
    // '0' -- and that one case exists to pin what an absent column becomes.
    // Scoped to the file rather than skipped outright: the rule is correct
    // everywhere the exact empty value carries no meaning.
    ->withSkip([
        SimplifyToLiteralBooleanRector::class => [
            __DIR__.'/tests/Unit/DTOs/SheetRowTest.php',
        ],
    ]);
