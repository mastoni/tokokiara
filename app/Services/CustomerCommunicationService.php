<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Sale;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerCommunicationService
{
    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail(Contact $customer)
    {
        if (!$customer->email) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'store' => $customer->store,
                'subject' => 'Welcome to ' . ($customer->store->name ?? 'Our Store'),
            ];

            Mail::send('emails.customer.welcome', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'welcome', 'Welcome email sent');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send purchase receipt email
     */
    public function sendReceiptEmail(Contact $customer, Sale $sale)
    {
        if (!$customer->email) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'sale' => $sale->load(['saleItems.product', 'store']),
                'subject' => 'Receipt - ' . $sale->invoice_number,
            ];

            Mail::send('emails.customer.receipt', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'receipt', 'Receipt sent for sale #' . $sale->invoice_number);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send receipt email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
            ]);
            return false;
        }
    }

    /**
     * Send payment reminder email
     */
    public function sendPaymentReminder(Contact $customer, $amount, $dueDate)
    {
        if (!$customer->email || $amount <= 0) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'amount' => $amount,
                'due_date' => $dueDate,
                'store' => $customer->store,
                'subject' => 'Payment Reminder - Outstanding Balance',
            ];

            Mail::send('emails.customer.payment_reminder', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'payment_reminder', "Payment reminder sent for amount: {$amount}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send promotional email campaign
     */
    public function sendPromotionalCampaign($customers, $campaignData)
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($customers as $customer) {
            if (!$customer->email || !$customer->is_active) {
                $failureCount++;
                continue;
            }

            try {
                $data = array_merge($campaignData, [
                    'customer' => $customer,
                    'store' => $customer->store,
                    'unsubscribe_token' => $this->generateUnsubscribeToken($customer),
                ]);

                Mail::send('emails.customer.promotional', $data, function($message) use ($customer, $campaignData) {
                    $message->to($customer->email)
                        ->subject($campaignData['subject']);
                });

                $this->logCommunication($customer, 'email', 'promotion', $campaignData['subject']);
                $successCount++;

            } catch (\Exception $e) {
                Log::error('Failed to send promotional email: ' . $e->getMessage(), [
                    'customer_id' => $customer->id,
                ]);
                $failureCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total_attempted' => $successCount + $failureCount,
        ];
    }

    /**
     * Send birthday wishes
     */
    public function sendBirthdayWishes(Contact $customer)
    {
        if (!$customer->email || !$customer->date_of_birth) {
            return false;
        }

        $today = now();
        $birthday = Carbon::parse($customer->date_of_birth);

        if ($birthday->month !== $today->month || $birthday->day !== $today->day) {
            return false;
        }

        try {
            // Add birthday bonus loyalty points
            $customer->addLoyaltyPoints(50, 'Birthday bonus points');

            $data = [
                'customer' => $customer,
                'store' => $customer->store,
                'birthday_points' => 50,
                'subject' => 'Happy Birthday from ' . ($customer->store->name ?? 'Our Store') . '!',
            ];

            Mail::send('emails.customer.birthday', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'birthday', 'Birthday wishes with 50 bonus points');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send birthday email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send loyalty points notification
     */
    public function sendLoyaltyPointsNotification(Contact $customer, LoyaltyPointTransaction $transaction)
    {
        if (!$customer->email) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'transaction' => $transaction,
                'store' => $customer->store,
                'subject' => $transaction->transaction_type === 'earned' ? 'You\'ve Earned Loyalty Points!' : 'Loyalty Points Redeemed',
            ];

            Mail::send('emails.customer.loyalty_points', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'loyalty_points', "Loyalty {$transaction->transaction_type}: {$transaction->points} points");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send loyalty points email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send abandoned cart reminder
     */
    public function sendAbandonedCartReminder(Contact $customer, $cartItems)
    {
        if (!$customer->email || empty($cartItems)) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'cart_items' => $cartItems,
                'store' => $customer->store,
                'subject' => 'Complete Your Purchase',
                'cart_url' => route('pos.cart.restore', ['customer_id' => $customer->id]),
            ];

            Mail::send('emails.customer.abandoned_cart', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'abandoned_cart', 'Abandoned cart reminder sent');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send abandoned cart email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send product recommendation email
     */
    public function sendProductRecommendations(Contact $customer, $recommendations)
    {
        if (!$customer->email || empty($recommendations)) {
            return false;
        }

        try {
            $data = [
                'customer' => $customer,
                'recommendations' => $recommendations,
                'store' => $customer->store,
                'subject' => 'Recommended Products Just For You',
            ];

            Mail::send('emails.customer.recommendations', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 'recommendations', 'Product recommendations sent');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send recommendations email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send re-engagement email to inactive customers
     */
    public function sendReEngagementEmail(Contact $customer)
    {
        if (!$customer->email) {
            return false;
        }

        // Check if customer is inactive (no purchases in last 90 days)
        $lastPurchase = $customer->sales()->completed()->max('sale_date');
        if ($lastPurchase && now()->diffInDays($lastPurchase) < 90) {
            return false;
        }

        try {
            // Add re-engagement bonus points
            $customer->addLoyaltyPoints(25, 'Re-engagement bonus');

            $data = [
                'customer' => $customer,
                'store' => $customer->store,
                'last_purchase_date' => $lastPurchase,
                'bonus_points' => 25,
                'subject' => 'We Miss You! Here\'s Something Special',
            ];

            Mail::send('emails.customer.re_engagement', $data, function($message) use ($customer, $data) {
                $message->to($customer->email)
                    ->subject($data['subject']);
            });

            $this->logCommunication($customer, 'email', 're_engagement', 'Re-engagement email with 25 bonus points');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send re-engagement email: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Send bulk SMS notifications (placeholder for SMS service integration)
     */
    public function sendSMSNotification(Contact $customer, $message, $type = 'general')
    {
        if (!$customer->phone) {
            return false;
        }

        try {
            // This would integrate with an SMS service like Twilio, Vonage, etc.
            // For now, logging the SMS attempt
            Log::info('SMS notification sent', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'message' => $message,
                'type' => $type,
            ]);

            $this->logCommunication($customer, 'sms', $type, $message);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send SMS: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
            return false;
        }
    }

    /**
     * Schedule automated communications
     */
    public function scheduleAutomatedCommunications()
    {
        $results = [];

        // Send birthday wishes
        $birthdayCustomers = Contact::customers()
            ->active()
            ->whereMonth('date_of_birth', now()->month)
            ->whereDay('date_of_birth', now()->day)
            ->get();

        $results['birthday_wishes'] = [
            'attempted' => $birthdayCustomers->count(),
            'sent' => 0,
        ];

        foreach ($birthdayCustomers as $customer) {
            if ($this->sendBirthdayWishes($customer)) {
                $results['birthday_wishes']['sent']++;
            }
        }

        // Send payment reminders
        $overdueCustomers = Contact::customers()
            ->active()
            ->where('balance', '<', -100) // Owe more than $100
            ->get();

        $results['payment_reminders'] = [
            'attempted' => $overdueCustomers->count(),
            'sent' => 0,
        ];

        foreach ($overdueCustomers as $customer) {
            if ($this->sendPaymentReminder($customer, abs($customer->balance), now())) {
                $results['payment_reminders']['sent']++;
            }
        }

        // Send re-engagement emails
        $inactiveCustomers = Contact::customers()
            ->active()
            ->whereHas('sales', function($query) {
                $query->completed()->where('sale_date', '<=', now()->subDays(90));
            })
            ->whereDoesntHave('sales', function($query) {
                $query->completed()->where('sale_date', '>', now()->subDays(90));
            })
            ->get();

        $results['re_engagement'] = [
            'attempted' => $inactiveCustomers->count(),
            'sent' => 0,
        ];

        foreach ($inactiveCustomers as $customer) {
            if ($this->sendReEngagementEmail($customer)) {
                $results['re_engagement']['sent']++;
            }
        }

        return $results;
    }

    /**
     * Get communication history for customer
     */
    public function getCommunicationHistory(Contact $customer, $limit = 50)
    {
        // This would query a communications table
        // For now, returning placeholder data structure
        return [
            'total_communications' => 0,
            'email_count' => 0,
            'sms_count' => 0,
            'recent_communications' => [],
        ];
    }

    /**
     * Generate unsubscribe token for email marketing
     */
    private function generateUnsubscribeToken(Contact $customer)
    {
        return hash('sha256', $customer->id . $customer->email . now());
    }

    /**
     * Log communication for tracking
     */
    private function logCommunication(Contact $customer, $channel, $type, $message)
    {
        DB::table('customer_communications')->insert([
            'contact_id' => $customer->id,
            'store_id' => $customer->store_id,
            'channel' => $channel,
            'type' => $type,
            'message' => $message,
            'sent_at' => now(),
            'created_by' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Get communication preferences for customer
     */
    public function getCommunicationPreferences(Contact $customer)
    {
        // This would query a customer_preferences table
        // For now, returning default preferences
        return [
            'email_marketing' => true,
            'email_receipts' => true,
            'email_promotions' => true,
            'sms_notifications' => false,
            'birthday_wishes' => true,
            'loyalty_updates' => true,
            'payment_reminders' => true,
        ];
    }

    /**
     * Update communication preferences
     */
    public function updateCommunicationPreferences(Contact $customer, $preferences)
    {
        // This would update a customer_preferences table
        // For now, just logging the update
        Log::info('Communication preferences updated', [
            'customer_id' => $customer->id,
            'preferences' => $preferences,
        ]);

        return true;
    }

    /**
     * Create customer segments for targeted marketing
     */
    public function createMarketingSegments()
    {
        $segments = [];

        // High-value customers
        $segments['high_value'] = Contact::customers()
            ->whereHas('sales', function($query) {
                $query->completed()->havingRaw('SUM(total_amount) > ?', [1000]);
            })
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        // Frequent buyers
        $segments['frequent_buyers'] = Contact::customers()
            ->whereHas('sales', function($query) {
                $query->completed()->havingRaw('COUNT(*) > ?', [10]);
            })
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        // New customers (last 30 days)
        $segments['new_customers'] = Contact::customers()
            ->whereHas('sales', function($query) {
                $query->completed()->where('sale_date', '>=', now()->subDays(30));
            })
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        // Inactive customers
        $segments['inactive'] = Contact::customers()
            ->whereHas('sales', function($query) {
                $query->completed()->where('sale_date', '<=', now()->subDays(90));
            })
            ->whereDoesntHave('sales', function($query) {
                $query->completed()->where('sale_date', '>', now()->subDays(90));
            })
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        return $segments;
    }
}