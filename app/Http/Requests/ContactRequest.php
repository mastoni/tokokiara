<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
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
        $contactId = $this->route('contact')?->id;

        return [
            'type' => ['required', 'in:customer,vendor'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')->ignore($contactId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[+]?[\d\s\-\(\)]+$/',
            ],
            'whatsapp' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[+]?[\d\s\-\(\)]+$/',
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('contacts', 'tax_number')->ignore($contactId),
            ],
            'company_name' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:-999999.99', 'max:999999.99'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'anniversary_date' => ['nullable', 'date'],
            'preferred_contact_method' => ['nullable', 'in:email,phone,whatsapp,sms'],
            'marketing_consent' => ['boolean'],
            'website' => ['nullable', 'url'],
            'social_media' => ['nullable', 'array'],
            'social_media.facebook' => ['nullable', 'url'],
            'social_media.twitter' => ['nullable', 'url'],
            'social_media.instagram' => ['nullable', 'url'],
            'social_media.linkedin' => ['nullable', 'url'],
            'custom_fields' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Contact type is required.',
            'type.in' => 'Contact type must be either customer or vendor.',
            'name.required' => 'Contact name is required.',
            'name.max' => 'Contact name must not exceed 255 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.regex' => 'Please enter a valid phone number.',
            'whatsapp.regex' => 'Please enter a valid WhatsApp number.',
            'tax_number.unique' => 'This tax number is already in use.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'preferred_contact_method.in' => 'Invalid preferred contact method.',
            'website.url' => 'Please enter a valid website URL.',
            'social_media.*.url' => 'Please enter a valid URL for social media.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'contact type',
            'name' => 'contact name',
            'email' => 'email address',
            'phone' => 'phone number',
            'whatsapp' => 'WhatsApp number',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'postal_code' => 'postal code',
            'tax_number' => 'tax number',
            'company_name' => 'company name',
            'balance' => 'balance',
            'loyalty_points' => 'loyalty points',
            'credit_limit' => 'credit limit',
            'payment_terms' => 'payment terms',
            'notes' => 'notes',
            'is_active' => 'active status',
            'date_of_birth' => 'date of birth',
            'anniversary_date' => 'anniversary date',
            'preferred_contact_method' => 'preferred contact method',
            'marketing_consent' => 'marketing consent',
            'website' => 'website',
            'social_media' => 'social media',
            'custom_fields' => 'custom fields',
            'tags' => 'tags',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateCreditLimit($validator);
            $this->validateContactInfo($validator);
            $this->validateSocialMedia($validator);
        });
    }

    /**
     * Validate credit limit against customer type
     */
    protected function validateCreditLimit($validator): void
    {
        if ($this->type === 'vendor' && $this->filled('credit_limit') && $this->credit_limit > 0) {
            $validator->errors()->add('credit_limit', 'Credit limit is typically for customers only.');
        }

        // For customers, credit limit should be reasonable
        if ($this->type === 'customer' && $this->filled('credit_limit') && $this->credit_limit > 100000) {
            $validator->errors()->add('credit_limit', 'Credit limit seems unusually high.');
        }
    }

    /**
     * Validate that at least one contact method is provided
     */
    protected function validateContactInfo($validator): void
    {
        if (!$this->filled('email') && !$this->filled('phone') && !$this->filled('whatsapp')) {
            $validator->errors()->add('contact_info', 'At least one contact method (email, phone, or WhatsApp) is required.');
        }

        // Validate phone format for different regions
        if ($this->filled('phone')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->phone);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $validator->errors()->add('phone', 'Phone number must be between 10 and 15 digits.');
            }
        }

        // WhatsApp should match phone if both provided
        if ($this->filled('phone') && $this->filled('whatsapp')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->phone);
            $whatsapp = preg_replace('/[^0-9+]/', '', $this->whatsapp);

            if ($phone !== $whatsapp && $this->boolean('marketing_consent')) {
                $validator->errors()->add('whatsapp', 'WhatsApp number should match phone number for marketing consent.');
            }
        }
    }

    /**
     * Validate social media URLs
     */
    protected function validateSocialMedia($validator): void
    {
        if (!$this->filled('social_media')) {
            return;
        }

        $socialMedia = $this->social_media;
        $validPlatforms = ['facebook', 'twitter', 'instagram', 'linkedin'];

        foreach ($socialMedia as $platform => $url) {
            if (!in_array($platform, $validPlatforms)) {
                $validator->errors()->add("social_media.{$platform}", 'Invalid social media platform.');
                continue;
            }

            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $validator->errors()->add("social_media.{$platform}", 'Please enter a valid URL for ' . ucfirst($platform));
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean numeric fields
        if ($this->filled('balance')) {
            $this->merge([
                'balance' => (float) str_replace(',', '', $this->balance),
            ]);
        }

        if ($this->filled('credit_limit')) {
            $this->merge([
                'credit_limit' => (float) str_replace(',', '', $this->credit_limit),
            ]);
        }

        if ($this->filled('loyalty_points')) {
            $this->merge([
                'loyalty_points' => (int) str_replace(',', '', $this->loyalty_points),
            ]);
        }

        // Clean phone numbers
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^\d+\-]/', '', $this->phone),
            ]);
        }

        if ($this->filled('whatsapp')) {
            $this->merge([
                'whatsapp' => preg_replace('/[^\d+\-]/', '', $this->whatsapp),
            ]);
        }

        // Set boolean values
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'marketing_consent' => $this->boolean('marketing_consent', false),
        ]);

        // Format social media URLs
        if ($this->filled('social_media')) {
            $socialMedia = $this->social_media;
            foreach ($socialMedia as $platform => &$url) {
                if (!empty($url) && !str_starts_with($url, 'http')) {
                    $url = 'https://' . $url;
                }
            }
            $this->merge(['social_media' => $socialMedia]);
        }

        // Set default payment terms for customers
        if ($this->type === 'customer' && !$this->filled('payment_terms')) {
            $this->merge(['payment_terms' => 'Net 30']);
        } elseif ($this->type === 'vendor' && !$this->filled('payment_terms')) {
            $this->merge(['payment_terms' => 'Net 15']);
        }

        // Set default preferred contact method
        if (!$this->filled('preferred_contact_method')) {
            if ($this->filled('email')) {
                $this->merge(['preferred_contact_method' => 'email']);
            } elseif ($this->filled('whatsapp')) {
                $this->merge(['preferred_contact_method' => 'whatsapp']);
            } elseif ($this->filled('phone')) {
                $this->merge(['preferred_contact_method' => 'phone']);
            }
        }

        // Clean tags array
        if ($this->filled('tags')) {
            $tags = array_filter($this->tags, function($tag) {
                return !empty(trim($tag));
            });
            $this->merge(['tags' => array_values($tags)]);
        }
    }

    /**
     * Get additional validation rules based on contact type
     */
    protected function getTypeSpecificRules(): array
    {
        $rules = [];

        if ($this->type === 'customer') {
            $rules = array_merge($rules, [
                'loyalty_points' => ['nullable', 'integer', 'min:0'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'anniversary_date' => ['nullable', 'date'],
                'marketing_consent' => ['boolean'],
            ]);
        }

        if ($this->type === 'vendor') {
            $rules = array_merge($rules, [
                'payment_terms' => ['required', 'string', 'max:100'],
                'tax_number' => ['required', 'string', 'max:50'],
                'bank_account' => ['nullable', 'string', 'max:100'],
                'bank_name' => ['nullable', 'string', 'max:100'],
            ]);
        }

        return $rules;
    }
}