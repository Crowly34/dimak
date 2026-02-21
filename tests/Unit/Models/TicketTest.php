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

test('ticket no longer has price attribute', function (): void {
    expect((new Ticket)->getFillable())->not->toContain('price');
});

test('ticket no longer has paid attribute', function (): void {
    expect((new Ticket)->getFillable())->not->toContain('paid');
});
