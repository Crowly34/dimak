<?php

namespace Database\Factories;

use App\Enums\OrderLocation;
use App\Enums\OrderStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'device' => fake()->randomElement(['MacBook Pro', 'MacBook Air', 'iMac', 'Mac mini']),
            'device_serial' => fake()->optional()->bothify('??######??##'),
            'device_password' => null,
            'received_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => fake()->optional()->sentence(),
            'observations' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(OrderStatus::cases())->value,
            'location' => fake()->randomElement(OrderLocation::cases())->value,
            'price' => fake()->optional()->randomFloat(2, 200, 5000),
            'paid' => fake()->boolean(30),
            'delivered_at' => null,
        ];
    }
}
