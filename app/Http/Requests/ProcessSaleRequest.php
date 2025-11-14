<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->canAccessPOS();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:contacts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,bank_transfer,credit,mobile_money,check,gift_card'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payments.*.note' => ['nullable', 'string', 'max:255'],
            // Card payment fields
            'payments.*.card_type' => ['required_if:payments.*.method,card', 'in:visa,mastercard,amex,discover'],
            'payments.*.card_last_four' => ['required_if:payments.*.method,card', 'string', 'size:4'],
            'payments.*.authorization_code' => ['nullable', 'string', 'max:50'],
            // Bank transfer fields
            'payments.*.bank_name' => ['required_if:payments.*.method,bank_transfer', 'string', 'max:100'],
            'payments.*.transfer_reference' => ['required_if:payments.*.method,bank_transfer', 'string', 'max:100'],
            'payments.*.account_number' => ['nullable', 'string', 'max:50'],
            // Mobile money fields
            'payments.*.provider' => ['required_if:payments.*.method,mobile_money', 'string', 'max:50'],
            'payments.*.phone_number' => ['required_if:payments.*.method,mobile_money', 'string', 'max:20'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            // Check fields
            'payments.*.check_number' => ['required_if:payments.*.method,check', 'string', 'max:50'],
            'payments.*.check_date' => ['required_if:payments.*.method,check', 'date'],
            // General sale fields
            'notes' => ['nullable', 'string', 'max:1000'],
            'staff_note' => ['nullable', 'string', 'max:1000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'apply_loyalty_points' => ['boolean'],
            'loyalty_points_to_use' => ['nullable', 'integer', 'min:0'],
            'hold_cart_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for the sale.',
            'items.min' => 'At least one item is required for the sale.',
            'items.*.product_id.required' => 'Product is required for all items.',
            'items.*.product_id.exists' => 'Selected product does not exist.',
            'items.*.quantity.required' => 'Quantity is required for all items.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.*.unit_price.required' => 'Price is required for all items.',
            'items.*.unit_price.min' => 'Price cannot be negative.',
            'payments.required' => 'At least one payment method is required.',
            'payments.*.method.required' => 'Payment method is required for all payments.',
            'payments.*.method.in' => 'Invalid payment method selected.',
            'payments.*.amount.required' => 'Payment amount is required.',
            'payments.*.amount.min' => 'Payment amount must be greater than 0.',
            'payments.*.card_type.required_if' => 'Card type is required for card payments.',
            'payments.*.card_last_four.required_if' => 'Card last four digits are required for card payments.',
            'payments.*.bank_name.required_if' => 'Bank name is required for bank transfers.',
            'payments.*.transfer_reference.required_if' => 'Transfer reference is required for bank transfers.',
            'payments.*.provider.required_if' => 'Mobile money provider is required.',
            'payments.*.phone_number.required_if' => 'Phone number is required for mobile money payments.',
            'payments.*.check_number.required_if' => 'Check number is required for check payments.',
            'payments.*.check_date.required_if' => 'Check date is required for check payments.',
            'delivery_date.after_or_equal' => 'Delivery date cannot be in the past.',
            'customer_id.exists' => 'Selected customer does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
            'items.*.discount' => 'discount',
            'items.*.tax_rate' => 'tax rate',
            'payments.*.method' => 'payment method',
            'payments.*.amount' => 'payment amount',
            'payments.*.card_type' => 'card type',
            'payments.*.card_last_four' => 'card last four digits',
            'payments.*.authorization_code' => 'authorization code',
            'payments.*.bank_name' => 'bank name',
            'payments.*.transfer_reference' => 'transfer reference',
            'payments.*.account_number' => 'account number',
            'payments.*.provider' => 'mobile money provider',
            'payments.*.phone_number' => 'phone number',
            'payments.*.reference' => 'reference number',
            'payments.*.check_number' => 'check number',
            'payments.*.check_date' => 'check date',
            'customer_id' => 'customer',
            'delivery_address' => 'delivery address',
            'delivery_date' => 'delivery date',
            'shipping_amount' => 'shipping amount',
            'discount_type' => 'discount type',
            'discount_value' => 'discount value',
            'loyalty_points_to_use' => 'loyalty points to use',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validatePaymentTotal($validator);
            $this->validateStockAvailability($validator);
            $this->validatePaymentMethods($validator);
            $this->validateCustomerCredit($validator);
            $this->validateLoyaltyPoints($validator);
        });
    }

    /**
     * Validate payment total matches sale total
     */
    protected function validatePaymentTotal($validator): void
    {
        if (!$this->has('items') || !$this->has('payments')) {
            return;
        }

        $calculatedTotal = 0;
        foreach ($this->items as $item) {
            $itemTotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
            $tax = $itemTotal * ($item['tax_rate'] ?? 0);
            $calculatedTotal += $itemTotal + $tax;
        }

        $calculatedTotal += $this->shipping_amount ?? 0;

        // Apply discount
        if ($this->discount_type === 'percentage' && $this->discount_value) {
            $calculatedTotal *= (1 - ($this->discount_value / 100));
        } elseif ($this->discount_type === 'fixed' && $this->discount_value) {
            $calculatedTotal -= $this->discount_value;
        }

        $calculatedTotal = max(0, $calculatedTotal);

        $totalPayment = collect($this->payments)->sum('amount');

        // Allow for cash overpayment (change)
        if ($totalPayment < $calculatedTotal) {
            if (!$this->customer_id) {
                $validator->errors()->add('payments', 'Insufficient payment amount. No customer selected for credit.');
            } else {
                $creditLimit = Contact::find($this->customer_id)?->credit_limit ?? 0;
                $balanceDue = $calculatedTotal - $totalPayment;

                if ($creditLimit > 0 && $balanceDue > $creditLimit) {
                    $validator->errors()->add('payments', "Payment amount exceeds customer credit limit of {$creditLimit}");
                }
            }
        }

        // Check for excessive overpayment
        if ($totalPayment > $calculatedTotal * 2) {
            $validator->errors()->add('payments', 'Payment amount seems unusually high.');
        }
    }

    /**
     * Validate stock availability
     */
    protected function validateStockAvailability($validator): void
    {
        foreach ($this->items as $index => $item) {
            $product = \App\Models\Product::find($item['product_id']);

            if (!$product) {
                $validator->errors()->add("items.{$index}.product_id", 'Product not found.');
                continue;
            }

            if (!$product->is_active) {
                $validator->errors()->add("items.{$index}.product_id", 'Product is not active.');
                continue;
            }

            if ($product->is_stock_managed && $product->quantity < $item['quantity']) {
                $validator->errors()->add("items.{$index}.quantity",
                    "Insufficient stock for {$product->name}. Available: {$product->quantity}, Requested: {$item['quantity']}");
            }
        }
    }

    /**
     * Validate payment methods
     */
    protected function validatePaymentMethods($validator): void
    {
        $creditPayments = collect($this->payments)->filter(fn($p) => $p['method'] === 'credit');

        if ($creditPayments->isNotEmpty() && !$this->customer_id) {
            $validator->errors()->add('payments', 'Credit payment requires customer selection.');
        }

        foreach ($this->payments as $index => $payment) {
            if ($payment['method'] === 'credit' && $this->customer_id) {
                $customer = \App\Models\Contact::find($this->customer_id);
                $creditLimit = $customer?->credit_limit ?? 0;
                $currentBalance = $customer?->balance ?? 0;
                $paymentAmount = $payment['amount'];

                if ($creditLimit > 0 && ($currentBalance + $paymentAmount) > $creditLimit) {
                    $validator->errors()->add("payments.{$index}.amount",
                        "Payment amount exceeds customer credit limit.");
                }
            }
        }
    }

    /**
     * Validate customer credit
     */
    protected function validateCustomerCredit($validator): void
    {
        if (!$this->customer_id) {
            return;
        }

        $customer = \App\Models\Contact::find($this->customer_id);
        if (!$customer || !$customer->isCustomer()) {
            $validator->errors()->add('customer_id', 'Invalid customer selected.');
            return;
        }

        if (!$customer->is_active) {
            $validator->errors()->add('customer_id', 'Customer account is inactive.');
        }
    }

    /**
     * Validate loyalty points
     */
    protected function validateLoyaltyPoints($validator): void
    {
        if (!$this->apply_loyalty_points || !$this->loyalty_points_to_use || !$this->customer_id) {
            return;
        }

        $customer = \App\Models\Contact::find($this->customer_id);
        if (!$customer || $customer->loyalty_points < $this->loyalty_points_to_use) {
            $validator->errors()->add('loyalty_points_to_use', 'Insufficient loyalty points.');
        }

        // Calculate loyalty point value (typically 1 point = $0.10)
        $pointValue = 0.10;
        $loyaltyValue = $this->loyalty_points_to_use * $pointValue;

        $calculatedTotal = collect($this->items)->sum(function($item) {
            return ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
        });

        if ($loyaltyValue > $calculatedTotal) {
            $validator->errors()->add('loyalty_points_to_use', 'Loyalty points cannot exceed sale total.');
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean numeric fields
        if ($this->has('shipping_amount')) {
            $this->merge([
                'shipping_amount' => (float) str_replace(',', '', $this->shipping_amount),
            ]);
        }

        if ($this->has('discount_value')) {
            $this->merge([
                'discount_value' => (float) str_replace(',', '', $this->discount_value),
            ]);
        }

        // Clean payment amounts
        if ($this->has('payments')) {
            $payments = $this->payments;
            foreach ($payments as $key => $payment) {
                if (isset($payment['amount'])) {
                    $payments[$key]['amount'] = (float) str_replace(',', '', $payment['amount']);
                }
            }
            $this->merge(['payments' => $payments]);
        }

        // Clean item quantities and prices
        if ($this->has('items')) {
            $items = $this->items;
            foreach ($items as $key => $item) {
                if (isset($item['quantity'])) {
                    $items[$key]['quantity'] = (float) str_replace(',', '', $item['quantity']);
                }
                if (isset($item['unit_price'])) {
                    $items[$key]['unit_price'] = (float) str_replace(',', '', $item['unit_price']);
                }
                if (isset($item['discount'])) {
                    $items[$key]['discount'] = (float) str_replace(',', '', $item['discount']);
                }
            }
            $this->merge(['items' => $items]);
        }

        // Set boolean values
        $this->merge([
            'apply_loyalty_points' => $this->boolean('apply_loyalty_points'),
        ]);
    }
}