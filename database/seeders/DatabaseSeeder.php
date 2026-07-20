<?php

namespace Database\Seeders;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<string> */
    private const FAULTS = [
        'No enciende',
        'Pantalla rota',
        'Se apaga solo',
        'No carga la batería',
        'Teclado dañado',
        'Daño por líquido',
        'Ventilador muy ruidoso',
        'Muy lenta, cliente pide SSD',
        'No da video',
        'Bisagra rota',
    ];

    /** @var list<string> */
    private const OBSERVATIONS = [
        'Esperando pieza, proveedor confirma 5 días',
        'Se cambió el SSD, equipo funcionando',
        'Cliente autoriza presupuesto por teléfono',
        'Board con corrosión, no se pudo reparar',
        'Equipo entregado, cliente conforme',
        'Pendiente de aprobación del cliente',
        'Se limpió y se cambió pasta térmica',
    ];

    /** @var list<string> */
    private const DEMO_PASSWORDS = [
        'clave123',
        'macbook2024',
        'dimak1234',
        'usuario01',
        'temporal99',
    ];

    /** @var list<string> */
    private const DEVICES = [
        'MacBook Pro',
        'MacBook Air',
        'iMac',
        'Mac mini',
        'Mac Studio',
        'iPhone 13',
        'iPhone 15',
        'iPad Pro',
    ];

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@dimak.test'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );

        // Round-robin pools, shuffled once, so every enum case gets genuine
        // coverage instead of leaving the tail cases to random chance.
        $statuses = TicketStatus::cases();
        shuffle($statuses);

        $nonDeliveredLocations = array_values(array_filter(
            TicketLocation::cases(),
            fn (TicketLocation $location): bool => $location !== TicketLocation::Delivered,
        ));
        shuffle($nonDeliveredLocations);

        $statusIndex = 0;
        $locationIndex = 0;

        $clients = Client::factory()->count(25)->create();

        foreach ($clients as $client) {
            $orderCount = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $orderCount; $i++) {
                $receivedAt = fake()->dateTimeBetween('-8 months', 'now');

                $order = Order::factory()->create([
                    'client_id' => $client->id,
                    'received_at' => $receivedAt,
                    'price' => fake()->randomFloat(2, 350, 9500),
                    'paid' => fake()->boolean(60),
                ]);

                // Weighted toward a single ticket, but frequent enough multi-ticket
                // orders to actually exercise the hasMany relationship in the UI.
                $ticketCount = fake()->randomElement([1, 1, 1, 2, 2, 3]);

                for ($t = 0; $t < $ticketCount; $t++) {
                    $status = $statuses[$statusIndex % count($statuses)];
                    $statusIndex++;

                    $isDelivered = $status === TicketStatus::Delivered;

                    if ($isDelivered) {
                        $location = TicketLocation::Delivered;
                    } else {
                        $location = $nonDeliveredLocations[$locationIndex % count($nonDeliveredLocations)];
                        $locationIndex++;
                    }

                    Ticket::factory()->create([
                        'order_id' => $order->id,
                        'device' => fake()->randomElement(self::DEVICES),
                        'status' => $status->value,
                        'location' => $location->value,
                        'description' => fake()->randomElement(self::FAULTS),
                        'observations' => fake()->optional(0.7)->randomElement(self::OBSERVATIONS),
                        'device_password' => fake()->optional(0.6)->randomElement(self::DEMO_PASSWORDS),
                        'delivered_at' => $isDelivered
                            ? fake()->dateTimeBetween($receivedAt, 'now')
                            : null,
                    ]);
                }
            }
        }
    }
}
