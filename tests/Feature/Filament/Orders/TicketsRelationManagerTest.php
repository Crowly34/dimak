<?php

use App\Enums\TicketStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\RelationManagers\TicketsRelationManager;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('can list tickets for an order', function (): void {
    $order = Order::factory()->create();
    $tickets = Ticket::factory()->count(3)->create(['order_id' => $order->id]);

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->assertCanSeeTableRecords($tickets);
});

test('can create a ticket', function (): void {
    $order = Order::factory()->create();

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('create', data: [
            'device' => 'MacBook Pro',
            'status' => TicketStatus::PendingDiagnosis->value,
        ])
        ->assertHasNoTableActionErrors();

    expect(Ticket::where('order_id', $order->id)->where('device', 'MacBook Pro')->exists())->toBeTrue();
});

test('can edit a ticket', function (): void {
    $order = Order::factory()->create();
    $ticket = Ticket::factory()->create(['order_id' => $order->id]);

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('edit', $ticket, data: [
            'device' => 'iMac',
        ])
        ->assertHasNoTableActionErrors();

    expect($ticket->fresh()->device)->toBe('iMac');
});

test('can delete a ticket', function (): void {
    $order = Order::factory()->create();
    $ticket = Ticket::factory()->create(['order_id' => $order->id]);

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('delete', $ticket)
        ->assertHasNoTableActionErrors();

    expect(Ticket::find($ticket->id))->toBeNull();
});

test('change status action creates a ticket status log', function (): void {
    $order = Order::factory()->create();
    $ticket = Ticket::factory()->create([
        'order_id' => $order->id,
        'status' => TicketStatus::PendingDiagnosis->value,
    ]);

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('change_status', $ticket, data: [
            'status' => TicketStatus::InProgress->value,
            'note' => 'Starting repair',
        ])
        ->assertHasNoTableActionErrors();

    expect(TicketStatusLog::where('ticket_id', $ticket->id)->exists())->toBeTrue();
    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

test('ticket form validates device is required', function (): void {
    $order = Order::factory()->create();

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('create', data: [
            'device' => null,
            'status' => TicketStatus::PendingDiagnosis->value,
        ])
        ->assertHasTableActionErrors(['device' => 'required']);
});

test('ticket form validates status is required', function (): void {
    $order = Order::factory()->create();

    livewire(TicketsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ])
        ->callTableAction('create', data: [
            'device' => 'MacBook Pro',
            'status' => null,
        ])
        ->assertHasTableActionErrors(['status' => 'required']);
});
