<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition()
    {
        $totalAmount = $this->faker->randomFloat(2, 10, 1000);
        $discount = $this->faker->optional(0.3)->randomFloat(2, 0, 50);
        $taxRate = $this->faker->randomFloat(3, 0, 0.25);
        $taxAmount = ($totalAmount - $discount) * $taxRate;
        $grandTotal = $totalAmount - $discount + $taxAmount;
        $amountReceived = $this->faker->randomFloat(2, 0, $grandTotal);

        return [
            'invoice_number' => 'INV-' . $this->faker->unique()->numerify('######'),
            'reference_id' => $this->faker->optional()->lexify('REF-?????'),
            'sale_type' => $this->faker->randomElement(['retail', 'wholesale', 'online']),
            'store_id' => Store::inRandomOrder()->first()?->id,
            'contact_id' => Contact::inRandomOrder()->customers()->first()?->id,
            'sale_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'sale_time' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'total_amount' => $totalAmount,
            'discount' => $discount,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $this->faker->optional(0.2)->randomFloat(2, 0, 50),
            'amount_received' => $amountReceived,
            'profit_amount' => $this->faker->randomFloat(2, 0, $totalAmount * 0.3),
            'status' => $this->faker->randomElement(['completed', 'pending', 'refunded', 'cancelled']),
            'payment_status' => $amountReceived >= $grandTotal ? 'paid' : ($amountReceived > 0 ? 'partial' : 'unpaid'),
            'note' => $this->faker->optional()->sentence(8),
            'staff_note' => $this->faker->optional()->sentence(10),
            'customer_note' => $this->faker->optional()->sentence(6),
            'loyalty_points_earned' => $this->faker->numberBetween(0, 50),
            'loyalty_points_redeemed' => $this->faker->numberBetween(0, 20),
            'delivery_address' => $this->faker->optional()->streetAddress(),
            'delivery_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'cart_snapshot' => [
                'items_count' => $this->faker->numberBetween(1, 10),
                'total_items' => $this->faker->numberBetween(1, 50),
            ],
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function completed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function refunded()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
        ]);
    }

    public function paid()
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'amount_received' => $attributes['total_amount'] + $attributes['tax_amount'] - $attributes['discount'],
        ]);
    }

    public function unpaid()
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'unpaid',
            'amount_received' => 0,
        ]);
    }

    public function partiallyPaid()
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'partial',
            'amount_received' => ($attributes['total_amount'] + $attributes['tax_amount'] - $attributes['discount']) * 0.5,
        ]);
    }

    public function today()
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => now(),
            'sale_time' => now(),
        ]);
    }

    public function thisMonth()
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => $this->faker->dateTimeBetween('first day of this month', 'now'),
            'sale_time' => $this->faker->dateTimeBetween('first day of this month', 'now'),
        ]);
    }
}