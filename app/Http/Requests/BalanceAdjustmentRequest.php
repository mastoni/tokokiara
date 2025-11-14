<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BalanceAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->canManageCustomers();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-999999.99', 'max:999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:credit,debit,payment,refund,adjustment,write_off'],
            'payment_method' => ['required_if:type,payment,refund', 'in:cash,card,bank_transfer,check,mobile_money'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'notify_customer' => ['boolean'],
            'create_invoice' => ['boolean'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Adjustment amount is required.',
            'amount.not_in' => 'Adjustment amount cannot be zero.',
            'amount.min' => 'Minimum adjustment amount is -999,999.99.',
            'amount.max' => 'Maximum adjustment amount is 999,999.99.',
            'reason.required' => 'Reason for adjustment is required.',
            'reason.max' => 'Reason must not exceed 255 characters.',
            'type.required' => 'Adjustment type is required.',
            'type.in' => 'Invalid adjustment type selected.',
            'payment_method.required_if' => 'Payment method is required for payments and refunds.',
            'payment_method.in' => 'Invalid payment method selected.',
            'reference_number.max' => 'Reference number must not exceed 100 characters.',
            'due_date.after_or_equal' => 'Due date cannot be in the past.',
            'notes.max' => 'Notes must not exceed 1000 characters.',
            'tax_amount.min' => 'Tax amount cannot be negative.',
            'discount_amount.min' => 'Discount amount cannot be negative.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'adjustment amount',
            'reason' => 'reason',
            'type' => 'adjustment type',
            'payment_method' => 'payment method',
            'reference_number' => 'reference number',
            'due_date' => 'due date',
            'notes' => 'notes',
            'notify_customer' => 'notify customer',
            'create_invoice' => 'create invoice',
            'tax_amount' => 'tax amount',
            'discount_amount' => 'discount amount',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAdjustmentLogic($validator);
            $this->validatePaymentDetails($validator);
            $this->validateCustomerPermissions($validator);
        });
    }

    /**
     * Validate adjustment logic
     */
    protected function validateAdjustmentLogic($validator): void
    {
        $amount = $this->amount;
        $type = $this->type;
        $contact = $this->route('contact');

        if (!$contact) {
            return;
        }

        // Validate amount direction based on type
        switch ($type) {
            case 'credit':
                if ($amount < 0) {
                    $validator->errors()->add('amount', 'Credit adjustment amount must be positive.');
                }
                break;

            case 'debit':
                if ($amount > 0) {
                    $validator->errors()->add('amount', 'Debit adjustment amount must be negative.');
                }
                break;

            case 'payment':
                if ($amount <= 0) {
                    $validator->errors()->add('amount', 'Payment amount must be positive.');
                }

                // Check if payment exceeds balance
                if ($contact->balance >= 0) {
                    $validator->errors()->add('type', 'Cannot apply payment to customer with positive balance.');
                }
                break;

            case 'refund':
                if ($amount >= 0) {
                    $validator->errors()->add('amount', 'Refund amount must be negative.');
                }
                break;

            case 'write_off':
                if ($contact->balance >= 0) {
                    $validator->errors()->add('type', 'Cannot write off positive balance.');
                }
                break;
        }

        // Validate credit limit for customers
        if ($contact->isCustomer() && $contact->credit_limit > 0) {
            $newBalance = $contact->balance + $amount;

            if ($newBalance < -abs($contact->credit_limit)) {
                $validator->errors()->add('amount',
                    'This adjustment would exceed the customer\'s credit limit of ' .
                    number_format(abs($contact->credit_limit), 2)
                );
            }
        }

        // Validate reasonable adjustment amounts
        if (abs($amount) > 100000) {
            $validator->errors()->add('amount',
                'Adjustment amount seems unusually high. Please verify the amount.'
            );
        }
    }

    /**
     * Validate payment-related details
     */
    protected function validatePaymentDetails($validator): void
    {
        if (!in_array($this->type, ['payment', 'refund'])) {
            return;
        }

        // Validate reference number for card payments
        if ($this->payment_method === 'card' && !$this->filled('reference_number')) {
            $validator->errors()->add('reference_number',
                'Authorization code or reference number is required for card payments.'
            );
        }

        // Validate check details for check payments
        if ($this->payment_method === 'check') {
            if (!$this->filled('reference_number')) {
                $validator->errors()->add('reference_number', 'Check number is required for check payments.');
            }
        }

        // Validate bank transfer details
        if ($this->payment_method === 'bank_transfer') {
            if (!$this->filled('reference_number')) {
                $validator->errors()->add('reference_number',
                    'Transfer reference number is required for bank transfers.'
                );
            }
        }

        // Due date is required for payments
        if ($this->type === 'payment' && !$this->filled('due_date')) {
            $validator->errors()->add('due_date', 'Due date is required for payment adjustments.');
        }
    }

    /**
     * Validate user permissions
     */
    protected function validateCustomerPermissions($validator): void
    {
        $contact = $this->route('contact');

        if (!$contact) {
            return;
        }

        $user = auth()->user();

        // Check if user can manage this customer's store
        if (!$user->canManageStore($contact->store_id)) {
            $validator->errors()->add('authorized',
                'You are not authorized to manage this customer\'s balance.'
            );
        }

        // Check for write-off permissions
        if ($this->type === 'write_off' && !$user->isAdmin()) {
            $validator->errors()->add('type',
                'Only administrators can perform write-off adjustments.'
            );
        }

        // Large adjustments require admin approval
        if (abs($this->amount) > 10000 && !$user->isAdmin()) {
            $validator->errors()->add('amount',
                'Adjustments over $10,000 require administrator approval.'
            );
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean numeric fields
        if ($this->filled('amount')) {
            $this->merge([
                'amount' => (float) str_replace(',', '', $this->amount),
            ]);
        }

        if ($this->filled('tax_amount')) {
            $this->merge([
                'tax_amount' => (float) str_replace(',', '', $this->tax_amount),
            ]);
        }

        if ($this->filled('discount_amount')) {
            $this->merge([
                'discount_amount' => (float) str_replace(',', '', $this->discount_amount),
            ]);
        }

        // Set boolean values
        $this->merge([
            'notify_customer' => $this->boolean('notify_customer', false),
            'create_invoice' => $this->boolean('create_invoice', false),
        ]);

        // Set default due date for payments
        if ($this->type === 'payment' && !$this->filled('due_date')) {
            $this->merge([
                'due_date' => now()->addDays(30), // Default 30 days
            ]);
        }

        // Clean reference number
        if ($this->filled('reference_number')) {
            $this->merge([
                'reference_number' => trim($this->reference_number),
            ]);
        }

        // Set default notes for different types
        if (!$this->filled('notes')) {
            $defaultNotes = [
                'credit' => 'Credit adjustment applied to customer account',
                'debit' => 'Debit adjustment applied to customer account',
                'payment' => 'Payment received from customer',
                'refund' => 'Refund issued to customer',
                'adjustment' => 'Balance adjustment',
                'write_off' => 'Bad debt written off',
            ];

            if (isset($defaultNotes[$this->type])) {
                $this->merge([
                    'notes' => $defaultNotes[$this->type],
                ]);
            }
        }
    }

    /**
     * Get custom validation rules based on adjustment type
     */
    protected function getTypeSpecificRules(): array
    {
        $rules = [];

        switch ($this->type) {
            case 'payment':
                $rules = array_merge($rules, [
                    'payment_method' => ['required', 'in:cash,card,bank_transfer,check,mobile_money'],
                    'due_date' => ['required', 'date', 'after_or_equal:today'],
                    'reference_number' => [
                        'required_if:payment_method,card,check,bank_transfer',
                        'string',
                        'max:100'
                    ],
                ]);
                break;

            case 'refund':
                $rules = array_merge($rules, [
                    'payment_method' => ['required', 'in:cash,card,bank_transfer,check'],
                    'reference_number' => [
                        'required_if:payment_method,card,check',
                        'string',
                        'max:100'
                    ],
                ]);
                break;

            case 'write_off':
                $rules = array_merge($rules, [
                    'reason' => ['required', 'string', 'max:255'],
                    'notes' => ['required', 'string', 'max:1000'],
                ]);
                break;
        }

        return $rules;
    }
}