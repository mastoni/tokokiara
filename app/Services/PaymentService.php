<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Transaction;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Process payment for a sale
     */
    public function processPayment(Sale $sale, array $payments)
    {
        $totalPayment = 0;
        $grandTotal = $sale->grand_total;
        $paymentMethods = [];

        DB::beginTransaction();

        try {
            foreach ($payments as $payment) {
                $amount = (float) $payment['amount'];
                $method = $payment['method'];

                if ($amount <= 0) {
                    throw new \Exception('Payment amount must be positive');
                }

                $totalPayment += $amount;

                // Validate payment method
                $this->validatePaymentMethod($method, $sale, $amount);

                // Process payment based on method
                $this->processPaymentMethod($sale, $payment);

                $paymentMethods[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'status' => 'processed',
                ];
            }

            // Check if payment covers the total
            if ($totalPayment < $grandTotal && !$sale->contact_id) {
                throw new \Exception('Insufficient payment amount for sale without customer');
            }

            // Update sale payment status
            if ($totalPayment >= $grandTotal) {
                $sale->payment_status = 'paid';
                $sale->status = 'completed';
            } elseif ($totalPayment > 0) {
                $sale->payment_status = 'partial';
            } else {
                $sale->payment_status = 'unpaid';
            }

            $sale->amount_received = $totalPayment;
            $sale->save();

            // Update customer balance if partial payment
            if ($sale->contact_id && $totalPayment < $grandTotal) {
                $balance = $grandTotal - $totalPayment;
                $customer = Contact::findOrFail($sale->contact_id);
                $customer->updateBalance($balance, 'Sale: ' . $sale->invoice_number);
            }

            DB::commit();

            return [
                'success' => true,
                'total_payment' => $totalPayment,
                'balance' => $grandTotal - $totalPayment,
                'payment_status' => $sale->payment_status,
                'payments' => $paymentMethods,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing error: ' . $e->getMessage(), [
                'sale_id' => $sale->id,
                'payments' => $payments,
            ]);

            throw $e;
        }
    }

    /**
     * Validate payment method
     */
    private function validatePaymentMethod($method, Sale $sale, $amount)
    {
        $validMethods = ['cash', 'card', 'bank_transfer', 'credit', 'mobile_money', 'check', 'gift_card'];

        if (!in_array($method, $validMethods)) {
            throw new \Exception("Invalid payment method: {$method}");
        }

        // Validate specific method requirements
        switch ($method) {
            case 'card':
                // Would validate card details here
                break;
            case 'bank_transfer':
                if (!$sale->contact_id) {
                    throw new \Exception('Bank transfer requires customer information');
                }
                break;
            case 'credit':
                if (!$sale->contact_id) {
                    throw new \Exception('Credit payment requires customer information');
                }
                // Check customer credit limit
                $customer = Contact::findOrFail($sale->contact_id);
                if ($customer->credit_limit > 0 && $customer->balance + $amount > $customer->credit_limit) {
                    throw new \Exception('Customer credit limit exceeded');
                }
                break;
            case 'check':
                // Would validate check details here
                break;
        }
    }

    /**
     * Process payment based on method
     */
    private function processPaymentMethod(Sale $sale, $payment)
    {
        $transactionData = [
            'sales_id' => $sale->id,
            'store_id' => $sale->store_id,
            'contact_id' => $sale->contact_id,
            'transaction_date' => now(),
            'amount' => $payment['amount'],
            'payment_method' => $payment['method'],
            'transaction_type' => 'payment',
            'note' => $payment['note'] ?? '',
            'created_by' => auth()->id(),
        ];

        // Add method-specific data
        switch ($payment['method']) {
            case 'card':
                $transactionData['card_type'] = $payment['card_type'] ?? null;
                $transactionData['card_last_four'] = $payment['card_last_four'] ?? null;
                $transactionData['authorization_code'] = $payment['authorization_code'] ?? null;
                break;

            case 'bank_transfer':
                $transactionData['bank_name'] = $payment['bank_name'] ?? null;
                $transactionData['transfer_reference'] = $payment['transfer_reference'] ?? null;
                $transactionData['account_number'] = $payment['account_number'] ?? null;
                break;

            case 'mobile_money':
                $transactionData['provider'] = $payment['provider'] ?? null;
                $transactionData['phone_number'] = $payment['phone_number'] ?? null;
                $transactionData['reference'] = $payment['reference'] ?? null;
                break;

            case 'check':
                $transactionData['check_number'] = $payment['check_number'] ?? null;
                $transactionData['bank_name'] = $payment['bank_name'] ?? null;
                $transactionData['check_date'] = $payment['check_date'] ?? null;
                break;

            case 'gift_card':
                $transactionData['gift_card_number'] = $payment['gift_card_number'] ?? null;
                $transactionData['gift_card_balance'] = $payment['gift_card_balance'] ?? null;
                break;
        }

        return Transaction::create($transactionData);
    }

    /**
     * Process refund
     */
    public function processRefund(Sale $sale, $amount, $reason, $refundMethod = 'original')
    {
        DB::beginTransaction();

        try {
            if (!$sale->canBeRefunded()) {
                throw new \Exception('This sale cannot be refunded');
            }

            if ($amount > $sale->amount_received) {
                throw new \Exception('Refund amount cannot exceed amount received');
            }

            // Determine refund method
            $paymentMethod = $refundMethod;
            if ($refundMethod === 'original') {
                $lastTransaction = $sale->transactions()->latest()->first();
                $paymentMethod = $lastTransaction->payment_method ?? 'cash';
            }

            // Create refund transaction
            $refundTransaction = Transaction::create([
                'sales_id' => $sale->id,
                'store_id' => $sale->store_id,
                'contact_id' => $sale->contact_id,
                'transaction_date' => now(),
                'amount' => -$amount,
                'payment_method' => $paymentMethod,
                'transaction_type' => 'refund',
                'note' => $reason,
                'created_by' => auth()->id(),
            ]);

            // Update sale
            $sale->amount_received = max(0, $sale->amount_received - $amount);

            if ($sale->amount_received <= 0) {
                $sale->payment_status = 'refunded';
            } else {
                $sale->payment_status = 'partial_refund';
            }

            $sale->save();

            // Update customer balance if applicable
            if ($sale->contact_id && $paymentMethod === 'credit') {
                $customer = Contact::findOrFail($sale->contact_id);
                $customer->updateBalance(-$amount, 'Refund: ' . $sale->invoice_number);
            }

            DB::commit();

            return [
                'success' => true,
                'refund_amount' => $amount,
                'refund_method' => $paymentMethod,
                'transaction' => $refundTransaction,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing error: ' . $e->getMessage(), [
                'sale_id' => $sale->id,
                'amount' => $amount,
            ]);

            throw $e;
        }
    }

    /**
     * Validate payment amount
     */
    public function validatePaymentAmount($amount, $saleTotal)
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be positive');
        }

        // Allow overpayment for cash (will give change)
        if ($amount > $saleTotal * 2) {
            throw new \Exception('Payment amount seems too high');
        }

        return true;
    }

    /**
     * Calculate change due
     */
    public function calculateChange($amountPaid, $totalDue)
    {
        if ($amountPaid <= $totalDue) {
            return 0;
        }

        return $amountPaid - $totalDue;
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods()
    {
        return [
            [
                'id' => 'cash',
                'name' => 'Cash',
                'icon' => 'money-bill-wave',
                'description' => 'Pay with cash',
                'enabled' => true,
            ],
            [
                'id' => 'card',
                'name' => 'Card',
                'icon' => 'credit-card',
                'description' => 'Pay with debit/credit card',
                'enabled' => true,
            ],
            [
                'id' => 'mobile_money',
                'name' => 'Mobile Money',
                'icon' => 'mobile-alt',
                'description' => 'Pay with mobile money',
                'enabled' => true,
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'icon' => 'university',
                'description' => 'Direct bank transfer',
                'enabled' => true,
                'requires_customer' => true,
            ],
            [
                'id' => 'credit',
                'name' => 'Credit',
                'icon' => 'hand-holding-usd',
                'description' => 'Charge to customer account',
                'enabled' => true,
                'requires_customer' => true,
            ],
            [
                'id' => 'check',
                'name' => 'Check',
                'icon' => 'file-invoice-dollar',
                'description' => 'Pay with check',
                'enabled' => true,
            ],
            [
                'id' => 'gift_card',
                'name' => 'Gift Card',
                'icon' => 'gift',
                'description' => 'Pay with gift card',
                'enabled' => false, // Would need gift card system
            ],
        ];
    }

    /**
     * Get payment method details
     */
    public function getPaymentMethodDetails($method)
    {
        $methods = $this->getPaymentMethods();
        return collect($methods)->firstWhere('id', $method);
    }

    /**
     * Validate payment method availability
     */
    public function isPaymentMethodAvailable($method, $sale = null)
    {
        $methodDetails = $this->getPaymentMethodDetails($method);

        if (!$methodDetails || !$methodDetails['enabled']) {
            return false;
        }

        // Check if method requires customer
        if ($methodDetails['requires_customer'] && (!$sale || !$sale->contact_id)) {
            return false;
        }

        return true;
    }

    /**
     * Process split payment
     */
    public function processSplitPayment(Sale $sale, array $payments)
    {
        $totalPayment = 0;
        $processedPayments = [];

        foreach ($payments as $payment) {
            try {
                $result = $this->processPayment($sale, [$payment]);
                $totalPayment += $payment['amount'];
                $processedPayments[] = [
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $processedPayments[] = [
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $totalPayment >= $sale->grand_total,
            'total_payment' => $totalPayment,
            'balance' => $sale->grand_total - $totalPayment,
            'payments' => $processedPayments,
        ];
    }

    /**
     * Get transaction summary for reporting
     */
    public function getTransactionSummary($startDate, $endDate, $storeId = null)
    {
        $query = Transaction::whereBetween('transaction_date', [$startDate, $endDate]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $transactions = $query->get();

        return [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('amount'),
            'payment_methods' => $transactions->groupBy('payment_method')->map(function($methodTransactions) {
                return [
                    'count' => $methodTransactions->count(),
                    'total' => $methodTransactions->sum('amount'),
                    'average' => $methodTransactions->avg('amount'),
                ];
            }),
            'transaction_types' => $transactions->groupBy('transaction_type')->map(function($typeTransactions) {
                return [
                    'count' => $typeTransactions->count(),
                    'total' => $typeTransactions->sum('amount'),
                ];
            }),
        ];
    }
}