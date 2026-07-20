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

test('device_password is stored encrypted and read back as plaintext', function (): void {
    $ticket = new Ticket;
    $ticket->device_password = 'secret123';

    expect($ticket->device_password)->toBe('secret123')
        ->and($ticket->getAttributes()['device_password'])->not->toBe('secret123')
        ->and(decrypt($ticket->getAttributes()['device_password']))->toBe('secret123');
});

test('device_password reads back as null when it cannot be decrypted', function (): void {
    $ticket = new Ticket;
    $ticket->setRawAttributes(['device_password' => 'not-a-valid-payload']);

    expect($ticket->device_password)->toBeNull();
});

test('an empty device_password is stored as null', function (): void {
    $ticket = new Ticket;
    $ticket->device_password = '';

    expect($ticket->getAttributes()['device_password'])->toBeNull()
        ->and($ticket->device_password)->toBeNull();
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
