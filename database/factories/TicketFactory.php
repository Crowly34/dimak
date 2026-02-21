<?php

namespace Database\Factories;

use App\Enums\TicketLocation;
use App\Enums\TicketStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'device' => fake()->randomElement(['MacBook Pro', 'MacBook Air', 'iMac', 'Mac mini', 'iPhone 15', 'iPad Pro']),
            'device_serial' => fake()->optional()->bothify('??######??##'),
            'device_password' => null,
            'delivered_at' => null,
            'description' => fake()->optional()->sentence(),
            'observations' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TicketStatus::cases())->value,
            'location' => TicketLocation::Shop->value,
            'price' => fake()->optional()->randomFloat(2, 200, 5000),
            'paid' => false,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TicketStatus::InProgress->value]);
    }

    public function ready(): static
    {
        return $this->state(['status' => TicketStatus::Ready->value]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => TicketStatus::Delivered->value,
            'location' => TicketLocation::Delivered->value,
            'delivered_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
