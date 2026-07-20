<?php

it('formats summary correctly', function () {
    $result = new \App\DTOs\SyncResult(created: 3, updated: 2, skipped: 980);
    expect($result->summary())->toBe('3 new, 2 updated, 980 unchanged');
});

it('defaults to zero counts', function () {
    $result = new \App\DTOs\SyncResult;
    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(0)
        ->and($result->skipped)->toBe(0);
});
