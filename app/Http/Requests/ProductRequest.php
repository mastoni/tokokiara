<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')->ignore($productId),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'reorder_point' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_stock' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['nullable', 'string', 'max:50'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_stock_managed' => ['boolean'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'product_type' => ['required', 'in:standard,service,digital'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'meta_data' => ['nullable', 'array'],
            'meta_data.color' => ['nullable', 'string', 'max:50'],
            'meta_data.size' => ['nullable', 'string', 'max:50'],
            'meta_data.weight' => ['nullable', 'numeric', 'min:0'],
            'meta_data.dimensions' => ['nullable', 'string', 'max:100'],
            'meta_data.warranty' => ['nullable', 'string', 'max:200'],
            'meta_data.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name must not exceed 255 characters.',
            'sku.unique' => 'This SKU is already in use.',
            'barcode.unique' => 'This barcode is already in use.',
            'price.required' => 'Selling price is required.',
            'price.min' => 'Price must be at least 0.',
            'cost.required' => 'Cost price is required.',
            'cost.min' => 'Cost must be at least 0.',
            'quantity.required' => 'Initial quantity is required.',
            'quantity.min' => 'Quantity cannot be negative.',
            'category_id.exists' => 'Selected category does not exist.',
            'brand_id.exists' => 'Selected brand does not exist.',
            'product_type.required' => 'Product type is required.',
            'product_type.in' => 'Invalid product type selected.',
            'tax_rate.max' => 'Tax rate must not exceed 100%.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image may not be greater than 2MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'brand_id' => 'brand',
            'is_stock_managed' => 'stock management',
            'is_active' => 'active status',
            'is_featured' => 'featured status',
            'alert_quantity' => 'alert quantity',
            'reorder_point' => 'reorder point',
            'max_stock' => 'maximum stock',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert checkboxes to boolean
        $this->merge([
            'is_stock_managed' => $this->boolean('is_stock_managed'),
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured'),
        ]);

        // Clean and format numeric fields
        if ($this->filled('price')) {
            $this->merge([
                'price' => (float) str_replace(',', '', $this->price),
            ]);
        }

        if ($this->filled('cost')) {
            $this->merge([
                'cost' => (float) str_replace(',', '', $this->cost),
            ]);
        }

        if ($this->filled('quantity')) {
            $this->merge([
                'quantity' => (float) str_replace(',', '', $this->quantity),
            ]);
        }

        if ($this->filled('alert_quantity')) {
            $this->merge([
                'alert_quantity' => (float) str_replace(',', '', $this->alert_quantity),
            ]);
        }

        if ($this->filled('reorder_point')) {
            $this->merge([
                'reorder_point' => (float) str_replace(',', '', $this->reorder_point),
            ]);
        }

        if ($this->filled('max_stock')) {
            $this->merge([
                'max_stock' => (float) str_replace(',', '', $this->max_stock),
            ]);
        }

        // Generate barcode if not provided
        if (!$this->filled('barcode') && $this->isMethod('post')) {
            $this->merge([
                'barcode' => 'PROD' . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            ]);
        }
    }
}