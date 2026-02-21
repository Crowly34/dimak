<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('order form shows folio, client, received_at fields only', function (): void {
    livewire(CreateOrder::class)
        ->assertFormFieldExists('folio')
        ->assertFormFieldExists('client_id')
        ->assertFormFieldExists('received_at')
        ->assertFormFieldDoesNotExist('device')
        ->assertFormFieldDoesNotExist('status')
        ->assertFormFieldDoesNotExist('price');
});

test('order table shows ticket count column', function (): void {
    livewire(ListOrders::class)
        ->assertTableColumnExists('tickets_count');
});

test('order table no longer shows device or status columns', function (): void {
    livewire(ListOrders::class)
        ->assertTableColumnDoesNotExist('device')
        ->assertTableColumnDoesNotExist('status');
});
