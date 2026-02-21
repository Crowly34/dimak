<?php

namespace Database\Factories;

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
            'received_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
