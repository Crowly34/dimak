<?php

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use App\Models\Ticket;

test('ticket belongs to an order', function (): void {
    $ticket = new Ticket;

    expect($ticket->order())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('ticket has many ticket status logs', function (): void {
    $ticket = new Ticket;

    expect($ticket->statusLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('device_password is encrypted and decrypted', function (): void {
    $ticket = new Ticket;
    $ticket->device_password = encrypt('secret123');

    expect($ticket->decryptedDevicePassword)->toBe('secret123');
});

test('device_password is excluded from audits', function (): void {
    $ticket = new Ticket;

    expect($ticket->getAuditExclude())->toContain('device_password');
});

test('status is cast to TicketStatus', function (): void {
    $casts = (new Ticket)->getCasts();

    expect($casts['status'])->toBe(TicketStatus::class);
});

test('location is cast to TicketLocation', function (): void {
    $casts = (new Ticket)->getCasts();

    expect($casts['location'])->toBe(TicketLocation::class);
});

test('price is cast to decimal', function (): void {
    $casts = (new Ticket)->getCasts();

    expect($casts['price'])->toBe('decimal:2');
});

test('paid is cast to boolean', function (): void {
    $casts = (new Ticket)->getCasts();

    expect($casts['paid'])->toBe('boolean');
});
