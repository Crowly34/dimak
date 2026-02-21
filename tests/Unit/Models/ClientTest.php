<?php

use App\Models\Client;

test('client has many orders', function (): void {
    $client = new Client;

    expect($client->orders())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('client fillable includes name, phone, and notes', function (): void {
    $fillable = (new Client)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('phone')
        ->toContain('notes');
});
