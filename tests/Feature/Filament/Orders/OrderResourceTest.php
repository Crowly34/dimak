<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Client;
use App\Models\Order;
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

test('can create an order', function (): void {
    $client = Client::factory()->create();

    livewire(CreateOrder::class)
        ->fillForm([
            'folio' => 'TEST-001',
            'client_id' => $client->id,
            'received_at' => '2025-01-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    expect(Order::where('folio', 'TEST-001')->where('client_id', $client->id)->exists())->toBeTrue();
});

test('order form requires folio and client', function (): void {
    livewire(CreateOrder::class)
        ->fillForm(['folio' => null, 'client_id' => null])
        ->call('create')
        ->assertHasFormErrors(['folio' => 'required', 'client_id' => 'required']);
});

test('can edit an order', function (): void {
    $order = Order::factory()->create(['folio' => 'OLD-001']);

    livewire(EditOrder::class, ['record' => $order->id])
        ->fillForm(['folio' => 'NEW-001'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($order->fresh()->folio)->toBe('NEW-001');
});

test('received_at filter narrows orders by date range', function (): void {
    $old = Order::factory()->create(['received_at' => '2024-01-01']);
    $recent = Order::factory()->create(['received_at' => '2025-06-01']);

    livewire(ListOrders::class)
        ->filterTable('received_at', ['from' => '2025-01-01', 'until' => '2025-12-31'])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});
