<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company() . ' Store',
            'address' => $this->faker->streetAddress(),
            'contact_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'sale_prefix' => $this->faker->lexify('SALE-?'),
            'current_sale_number' => $this->faker->numberBetween(1, 1000),
            'tax_rate' => $this->faker->randomFloat(3, 0, 0.25),
            'currency_code' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'IDR']),
            'is_active' => true,
            'settings' => [
                'receipt_header' => $this->faker->company(),
                'receipt_footer' => 'Thank you for your purchase!',
                'allow_negative_stock' => false,
                'auto_backup' => true,
                'theme' => $this->faker->randomElement(['light', 'dark']),
            ],
        ];
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSalePrefix($prefix)
    {
        return $this->state(fn (array $attributes) => [
            'sale_prefix' => $prefix,
        ]);
    }
}