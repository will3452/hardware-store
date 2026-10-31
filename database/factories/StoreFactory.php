<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('STORE-####-????'),
            'display_name' => fake()->company(),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'status' => 'active',
        ];
    }
}
