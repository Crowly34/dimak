<?php

it('creates a SheetRow from a raw array', function () {
    $row = ['1001', 'MacBook Pro', 'John Doe', '555-1234', '15/01/2025', 'Screen broken', 'C02ABC', 'secret123', 'en proceso'];
    $dto = \App\DTOs\SheetRow::fromArray($row);

    expect($dto->folio)->toBe('1001')
        ->and($dto->device)->toBe('MacBook Pro')
        ->and($dto->clientName)->toBe('John Doe')
        ->and($dto->clientPhone)->toBe('555-1234')
        ->and($dto->receivedAt)->toBe('2025-01-15')
        ->and($dto->description)->toBe('Screen broken')
        ->and($dto->deviceSerial)->toBe('C02ABC')
        ->and($dto->devicePassword)->toBe('secret123')
        ->and($dto->observations)->toBe('en proceso');
});

it('handles missing columns gracefully', function () {
    $row = ['1002', 'iMac'];
    $dto = \App\DTOs\SheetRow::fromArray($row);

    expect($dto->folio)->toBe('1002')
        ->and($dto->device)->toBe('iMac')
        ->and($dto->clientName)->toBe('')
        ->and($dto->devicePassword)->toBe('');
});

it('normalizes folio by stripping periods from numeric folios', function () {
    $row = ['1.598', 'MacBook', '', '', '', '', '', '', ''];
    $dto = \App\DTOs\SheetRow::fromArray($row);
    expect($dto->folio)->toBe('1598');
});

it('preserves non-numeric folios as-is', function () {
    $row = ['3600Ñ', 'MacBook', '', '', '', '', '', '', ''];
    $dto = \App\DTOs\SheetRow::fromArray($row);
    expect($dto->folio)->toBe('3600Ñ');
});

it('computes consistent hash for same data', function () {
    $row = ['1001', 'MacBook', 'John', '555', '01/01/2025', 'desc', 'serial', 'pass', 'obs'];
    $dto1 = \App\DTOs\SheetRow::fromArray($row);
    $dto2 = \App\DTOs\SheetRow::fromArray($row);
    expect($dto1->hash)->toBe($dto2->hash);
});

it('computes different hash when data changes', function () {
    $row1 = ['1001', 'MacBook', 'John', '555', '', '', '', '', ''];
    $row2 = ['1001', 'MacBook', 'John', '555', '', '', '', '', 'updated observations'];
    expect(\App\DTOs\SheetRow::fromArray($row1)->hash)
        ->not->toBe(\App\DTOs\SheetRow::fromArray($row2)->hash);
});

it('parses d/m/Y date format', function () {
    $row = ['1001', '', '', '', '15/01/2025', '', '', '', ''];
    $dto = \App\DTOs\SheetRow::fromArray($row);
    expect($dto->receivedAt)->toBe('2025-01-15');
});

it('returns null for unparseable dates', function () {
    $row = ['1001', '', '', '', 'not-a-date', '', '', '', ''];
    $dto = \App\DTOs\SheetRow::fromArray($row);
    expect($dto->receivedAt)->toBeNull();
});
