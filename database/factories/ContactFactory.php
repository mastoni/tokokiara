<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'whatsapp' => $this->faker->phoneNumber(),
            'type' => $this->faker->randomElement(['customer', 'vendor']),
            'balance' => $this->faker->randomFloat(2, -1000, 1000),
            'loyalty_points' => $this->faker->numberBetween(0, 500),
            'credit_limit' => $this->faker->optional(0.6)->randomFloat(2, 100, 5000),
            'payment_terms' => $this->faker->randomElement(['Net 7', 'Net 15', 'Net 30', 'COD']),
            'tax_number' => $this->faker->optional(0.7)->numerify('Tax-########'),
            'company_name' => $this->faker->optional(0.4)->company(),
            'notes' => $this->faker->optional(0.3)->sentence(10),
            'is_active' => true,
            'store_id' => Store::inRandomOrder()->first()?->id,
        ];
    }

    public function customer()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'customer',
        ]);
    }

    public function vendor()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vendor',
        ]);
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withBalance($balance)
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    public function withLoyaltyPoints($points)
    {
        return $this->state(fn (array $attributes) => [
            'loyalty_points' => $points,
        ]);
    }

    public function company()
    {
        return $this->state(fn (array $attributes) => [
            'company_name' => $this->faker->company(),
            'name' => $this->faker->name(), // Contact person name
        ]);
    }
}