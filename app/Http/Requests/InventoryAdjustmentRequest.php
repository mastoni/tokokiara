<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $isBulk = $this->has('adjustments');

        if ($isBulk) {
            return [
                'adjustments' => ['required', 'array', 'min:1'],
                'adjustments.*.product_id' => ['required', 'exists:products,id'],
                'adjustments.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'adjustments.*.reason' => ['required', 'string', 'max:255'],
                'transaction_type' => ['required', 'in:adjustment,damage,expiry'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return [
            'product_id' => ['required', 'exists:products,id'],
            'store_id' => ['required', 'exists:stores,id'],
            'adjustment_type' => ['required', 'in:add,subtract,set'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'in:adjustment,damage,expiry,transfer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'to_store_id' => [
                'required_if:transaction_type,transfer',
                'exists:stores,id',
                'different:from_store_id',
            ],
            'from_store_id' => [
                'required_if:transaction_type,transfer',
                'exists:stores,id',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product selection is required.',
            'product_id.exists' => 'Selected product does not exist.',
            'store_id.required' => 'Store selection is required.',
            'store_id.exists' => 'Selected store does not exist.',
            'adjustment_type.required' => 'Adjustment type is required.',
            'adjustment_type.in' => 'Invalid adjustment type selected.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reason.required' => 'Reason for adjustment is required.',
            'reason.max' => 'Reason must not exceed 255 characters.',
            'transaction_type.required' => 'Transaction type is required.',
            'transaction_type.in' => 'Invalid transaction type selected.',
            'adjustments.required' => 'At least one adjustment is required.',
            'adjustments.min' => 'At least one adjustment is required.',
            'adjustments.*.product_id.required' => 'Product selection is required for all adjustments.',
            'adjustments.*.product_id.exists' => 'Selected product does not exist.',
            'adjustments.*.quantity.required' => 'Quantity is required for all adjustments.',
            'adjustments.*.quantity.min' => 'Quantity cannot be negative.',
            'adjustments.*.reason.required' => 'Reason is required for all adjustments.',
            'to_store_id.required_if' => 'Destination store is required for transfers.',
            'to_store_id.exists' => 'Selected destination store does not exist.',
            'to_store_id.different' => 'Source and destination stores must be different.',
            'from_store_id.required_if' => 'Source store is required for transfers.',
            'from_store_id.exists' => 'Selected source store does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'adjustments' => 'adjustments',
            'adjustments.*.product_id' => 'product',
            'adjustments.*.quantity' => 'quantity',
            'adjustments.*.reason' => 'reason',
            'to_store_id' => 'destination store',
            'from_store_id' => 'source store',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate products in bulk adjustment
            if ($this->has('adjustments')) {
                $productIds = array_column($this->adjustments, 'product_id');
                if (count($productIds) !== count(array_unique($productIds))) {
                    $validator->errors()->add('adjustments', 'Duplicate products found in adjustments. Each product can only be adjusted once.');
                }
            }

            // Validate sufficient stock for transfers
            if ($this->transaction_type === 'transfer' && $this->filled(['product_id', 'from_store_id', 'quantity'])) {
                $product = \App\Models\Product::find($this->product_id);
                if ($product) {
                    $fromStoreStock = \App\Models\InventoryItemStore::where('inventory_item_id', $product->id)
                        ->where('store_id', $this->from_store_id)
                        ->value('quantity') ?? 0;

                    if ($fromStoreStock < $this->quantity) {
                        $validator->errors()->add('quantity', "Insufficient stock in source store. Available: {$fromStoreStock}, Requested: {$this->quantity}");
                    }
                }
            }

            // Validate logical constraints for single adjustments
            if (!$this->has('adjustments')) {
                if ($this->adjustment_type === 'subtract' && $this->filled(['product_id', 'store_id', 'quantity'])) {
                    $product = \App\Models\Product::find($this->product_id);
                    if ($product && $product->quantity < $this->quantity) {
                        $validator->errors()->add('quantity', "Cannot subtract more than current stock. Current stock: {$product->quantity}, Requested: {$this->quantity}");
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean numeric fields
        if ($this->filled('quantity')) {
            $this->merge([
                'quantity' => (float) str_replace(',', '', $this->quantity),
            ]);
        }

        // Clean bulk adjustment quantities
        if ($this->has('adjustments')) {
            $adjustments = $this->adjustments;
            foreach ($adjustments as $key => $adjustment) {
                if (isset($adjustment['quantity'])) {
                    $adjustments[$key]['quantity'] = (float) str_replace(',', '', $adjustment['quantity']);
                }
            }
            $this->merge(['adjustments' => $adjustments]);
        }
    }
}