<?php

use App\Models\Order;

test('order has many tickets', function (): void {
    $order = new Order;

    expect($order->tickets())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('order belongs to client', function (): void {
    $order = new Order;

    expect($order->client())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('order no longer has device attribute', function (): void {
    $fillable = (new Order)->getFillable();

    expect($fillable)->not->toContain('device');
});

test('order no longer has status attribute', function (): void {
    $fillable = (new Order)->getFillable();

    expect($fillable)->not->toContain('status');
});
