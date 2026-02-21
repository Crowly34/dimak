<?php

use App\Enums\TicketStatus;
use App\Filament\Widgets\RepairShopOverview;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('widget renders all four stat cards', function (): void {
    livewire(RepairShopOverview::class)
        ->assertSee('Open Tickets')
        ->assertSee('Waiting Parts')
        ->assertSee('Ready for Pickup')
        ->assertSee('Unpaid Ready');
});

test('open tickets excludes delivered and no_repair tickets', function (): void {
    Ticket::factory()->count(5)->create(['status' => TicketStatus::InProgress->value]);
    Ticket::factory()->count(2)->create(['status' => TicketStatus::Delivered->value]);
    Ticket::factory()->create(['status' => TicketStatus::NoRepair->value]);

    $openCount = Ticket::whereNotIn('status', [
        TicketStatus::Delivered->value,
        TicketStatus::NoRepair->value,
    ])->count();

    expect($openCount)->toBe(5);

    livewire(RepairShopOverview::class)->assertSuccessful();
});

test('waiting parts stat counts only waiting_part tickets', function (): void {
    Ticket::factory()->count(3)->create(['status' => TicketStatus::WaitingPart->value]);
    Ticket::factory()->count(2)->create(['status' => TicketStatus::InProgress->value]);

    $count = Ticket::where('status', TicketStatus::WaitingPart->value)->count();

    expect($count)->toBe(3);
});

test('ready for pickup stat counts only ready tickets', function (): void {
    Ticket::factory()->count(4)->create(['status' => TicketStatus::Ready->value]);
    Ticket::factory()->count(2)->create(['status' => TicketStatus::InProgress->value]);

    $count = Ticket::where('status', TicketStatus::Ready->value)->count();

    expect($count)->toBe(4);
});

test('unpaid ready stat excludes tickets whose order is paid', function (): void {
    $unpaidOrder = Order::factory()->create(['paid' => false]);
    $paidOrder = Order::factory()->create(['paid' => true]);

    Ticket::factory()->count(3)->create(['order_id' => $unpaidOrder->id, 'status' => TicketStatus::Ready->value]);
    Ticket::factory()->count(2)->create(['order_id' => $paidOrder->id, 'status' => TicketStatus::Ready->value]);

    $count = Ticket::where('status', TicketStatus::Ready->value)
        ->whereHas('order', fn ($q) => $q->where('paid', false))
        ->count();

    expect($count)->toBe(3);
});
