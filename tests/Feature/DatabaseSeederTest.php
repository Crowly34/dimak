<?php

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('database seeder produces a convincing demo dataset', function (): void {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    expect(User::where('email', 'admin@dimak.test')->exists())->toBeTrue()
        ->and(Client::count())->toBe(25)
        ->and(Order::count())->toBeGreaterThanOrEqual(25)
        ->and(Ticket::count())->toBeGreaterThanOrEqual(Order::count());

    $ticketCountsByOrder = Ticket::pluck('order_id')->countBy();
    expect($ticketCountsByOrder->filter(fn (int $count): bool => $count > 1))->not->toBeEmpty();

    foreach (TicketStatus::cases() as $status) {
        expect(Ticket::where('status', $status->value)->exists())
            ->toBeTrue("Expected at least one ticket with status {$status->value}");
    }

    expect(Order::where('paid', true)->exists())->toBeTrue()
        ->and(Order::where('paid', false)->exists())->toBeTrue();
});
