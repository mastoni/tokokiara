<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(10),
            'sku' => $this->faker->unique()->lexify('SKU-?????'),
            'barcode' => $this->faker->unique()->ean13(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'cost' => $this->faker->randomFloat(2, 5, 500),
            'quantity' => $this->faker->numberBetween(0, 100),
            'alert_quantity' => $this->faker->numberBetween(5, 20),
            'reorder_point' => $this->faker->numberBetween(10, 30),
            'max_stock' => $this->faker->numberBetween(100, 500),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'ltr', 'box', 'pack']),
            'tax_rate' => $this->faker->randomFloat(3, 0, 0.25),
            'discount' => $this->faker->optional(0.3)->randomFloat(2, 0, 50),
            'is_active' => true,
            'is_stock_managed' => $this->faker->boolean(80),
            'is_featured' => $this->faker->boolean(20),
            'product_type' => $this->faker->randomElement(['standard', 'service', 'digital']),
            'category_id' => Category::inRandomOrder()->first()?->id,
            'brand_id' => Brand::inRandomOrder()->first()?->id,
            'meta_data' => [
                'color' => $this->faker->safeColorName(),
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'weight' => $this->faker->randomFloat(2, 0.1, 10),
            ],
        ];
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function lowStock()
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->numberBetween(0, 5),
            'alert_quantity' => $this->faker->numberBetween(10, 20),
            'is_stock_managed' => true,
        ]);
    }

    public function outOfStock()
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'is_stock_managed' => true,
        ]);
    }

    public function notStockManaged()
    {
        return $this->state(fn (array $attributes) => [
            'is_stock_managed' => false,
        ]);
    }

    public function featured()
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}