<?php

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('client form has name, phone, and notes fields', function (): void {
    livewire(CreateClient::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('phone')
        ->assertFormFieldExists('notes');
});

test('creating a client requires a name', function (): void {
    livewire(CreateClient::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('can create a client', function (): void {
    livewire(CreateClient::class)
        ->fillForm(['name' => 'Ana García', 'phone' => '555-0001'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    expect(Client::where('name', 'Ana García')->where('phone', '555-0001')->exists())->toBeTrue();
});

test('can edit a client', function (): void {
    $client = Client::factory()->create(['name' => 'Old Name']);

    livewire(EditClient::class, ['record' => $client->id])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($client->fresh()->name)->toBe('New Name');
});

test('client table shows name, phone, and order count columns', function (): void {
    livewire(ListClients::class)
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('phone')
        ->assertTableColumnExists('orders_count');
});

test('client table lists all clients', function (): void {
    $clients = Client::factory()->count(3)->create();

    livewire(ListClients::class)
        ->assertCanSeeTableRecords($clients);
});

test('client table is searchable by name', function (): void {
    $clients = Client::factory()->count(3)->create();

    livewire(ListClients::class)
        ->searchTable($clients->first()->name)
        ->assertCanSeeTableRecords($clients->take(1))
        ->assertCanNotSeeTableRecords($clients->skip(1));
});
