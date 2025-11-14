<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(6),
            'code' => $this->faker->unique()->lexify('CAT-???'),
            'is_active' => true,
            'parent_id' => null,
        ];
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function asChild()
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Category::inRandomOrder()->whereNull('parent_id')->first()?->id,
        ]);
    }

    public function withParent($parentId)
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}