<?php

use App\Models\TicketStatusLog;

test('ticket status log belongs to a ticket', function (): void {
    $log = new TicketStatusLog;

    expect($log->ticket())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('ticket status log has from_status and to_status', function (): void {
    $fillable = (new TicketStatusLog)->getFillable();

    expect($fillable)->toContain('from_status')
        ->and($fillable)->toContain('to_status');
});
