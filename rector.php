<?php

declare(strict_types=1);

use Pest\Rector\Rules\SimplifyToLiteralBooleanRector;
use Pest\Rector\Rules\UseToBeEmptyRector;
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
    // Both rules rewrite toBe('') into toBeEmpty(), which also passes for 0,
    // '0', [] and false. SheetRowTest pins the exact value absent spreadsheet
    // columns coerce to, so the rewrite drops the guarantee under test. They
    // are skipped as a pair because either will fire once the other is gone.
    ->withSkip([
        SimplifyToLiteralBooleanRector::class,
        UseToBeEmptyRector::class,
    ]);
