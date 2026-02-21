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

test('order has price attribute', function (): void {
    expect((new Order)->getFillable())->toContain('price');
});

test('order has paid attribute', function (): void {
    expect((new Order)->getFillable())->toContain('paid');
});

test('price is cast to decimal on order', function (): void {
    expect((new Order)->getCasts()['price'])->toBe('decimal:2');
});

test('paid is cast to boolean on order', function (): void {
    expect((new Order)->getCasts()['paid'])->toBe('boolean');
});
