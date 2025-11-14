<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;
    use App\Traits\Userstamps;
    
    protected $fillable = [
        'invoice_number',
        'reference_id',
        'sale_type',
        'store_id',        // Store ID
        'contact_id',     // Customer ID
        'sale_date',       // Sale date
        'total_amount',    //Net total (total after discount)
        'discount',        // Discount
        'amount_received',  // Amount received
        'profit_amount',   // Profit amount
        'status',          // Sale status ['completed', 'pending', 'refunded']
        'payment_status',
        'note',        // Note
        'created_by',
        'deleted_by',
        'cart_snapshot',
        'sale_time',
        'tax_amount',
        'shipping_amount',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'staff_note',
        'customer_note',
        'delivery_address',
        'delivery_date',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'sale_time' => 'datetime',
        'delivery_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'loyalty_points_earned' => 'integer',
        'loyalty_points_redeemed' => 'integer',
        'cart_snapshot' => 'array',
    ];

    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_UNPAID = 'unpaid';

    protected static function boot()
    {
        parent::boot();

        static::created(function ($sale) {
            DB::transaction(function () use ($sale) {
                $store = Store::find($sale->store_id);
                if ($store) {
                    $sale_number = $store->current_sale_number+1;
                    $year = $sale->sale_date instanceof \Carbon\Carbon 
                        ? $sale->sale_date->year 
                        : \Carbon\Carbon::parse($sale->sale_date)->year;
                    $padded_sale_id = str_pad($sale->id, 4, '0', STR_PAD_LEFT);
                    // Generate the invoice number based on the sale prefix and current sale number
                    $sale->invoice_number = $year.'/'.$padded_sale_id.'/'.str_pad($sale_number, 4, '0', STR_PAD_LEFT);

                    // Save the updated invoice number
                    $sale->save();

                    // Increment the current sale number in the store
                    $store->increment('current_sale_number');
                }
            });
        });
    }

    // public function getUpdatedAtAttribute($value)
    // {
    //     return \Carbon\Carbon::parse($value)->format('Y-m-d'); // Adjust the format as needed
    // }

    // // Accessor for formatted created_at date
    // public function getCreatedAtAttribute($value)
    // {
    //     return \Carbon\Carbon::parse($value)->format('Y-m-d'); // Adjust the format as needed
    // }

    // // Accessor for formatted sale_date date
    // public function getSaleDateAttribute($value)
    // {
    //     return \Carbon\Carbon::parse($value)->format('Y-m-d'); // Adjust the format as needed
    // }

    // Relationship to transactions
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'sales_id');
    }

    // Relationship to sale items
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
    
    // Scope to filter sales by store_id
    public function scopeStoreId($query, $storeId)
    {
        if ($storeId !== 'All' && $storeId !== 0) {
            return $query->where('store_id', $storeId);
        }
        return $query;
    }

    
    // Scope to filter sales by start_date and end_date
    public function scopeDateFilter($query, $start_date, $end_date)
    {
        if (!empty($start_date) && !empty($end_date)) {
            return $query->whereBetween('sale_date', [$start_date, $end_date]);
        }
        return $query;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('contact_id', $customerId);
    }

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2);
    }

    public function getFormattedDiscountAttribute()
    {
        return number_format($this->discount, 2);
    }

    public function getFormattedTaxAttribute()
    {
        return number_format($this->tax_amount, 2);
    }

    public function getGrandTotalAttribute()
    {
        return $this->total_amount + $this->tax_amount + $this->shipping_amount - $this->discount;
    }

    public function getFormattedGrandTotalAttribute()
    {
        return number_format($this->grand_total, 2);
    }

    public function getBalanceAttribute()
    {
        return $this->grand_total - $this->amount_received;
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance, 2);
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function getPaymentStatusLabelAttribute()
    {
        return ucfirst($this->payment_status);
    }

    public function getIsOverdueAttribute()
    {
        return $this->payment_status === self::PAYMENT_UNPAID &&
               $this->sale_date->lt(now()->subDays(30));
    }

    // Methods
    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRefunded()
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isPaid()
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isPartiallyPaid()
    {
        return $this->payment_status === self::PAYMENT_PARTIAL;
    }

    public function isUnpaid()
    {
        return $this->payment_status === self::PAYMENT_UNPAID;
    }

    public function canBeRefunded()
    {
        return $this->isCompleted() && $this->isPaid();
    }

    public function canBeEdited()
    {
        return $this->isPending();
    }

    public function canBeCancelled()
    {
        return $this->isPending() || $this->isUnpaid();
    }

    public function addPayment($amount, $paymentMethod, $note = '')
    {
        $newAmountReceived = $this->amount_received + $amount;
        $grandTotal = $this->grand_total;

        // Update payment status
        if ($newAmountReceived >= $grandTotal) {
            $paymentStatus = self::PAYMENT_PAID;
        } elseif ($newAmountReceived > 0) {
            $paymentStatus = self::PAYMENT_PARTIAL;
        } else {
            $paymentStatus = self::PAYMENT_UNPAID;
        }

        $this->update([
            'amount_received' => $newAmountReceived,
            'payment_status' => $paymentStatus,
        ]);

        // Create transaction record
        Transaction::create([
            'sales_id' => $this->id,
            'store_id' => $this->store_id,
            'contact_id' => $this->contact_id,
            'transaction_date' => now(),
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_type' => 'payment',
            'note' => $note,
            'created_by' => auth()->id(),
        ]);

        return $this;
    }

    public function refund($reason = '')
    {
        if (!$this->canBeRefunded()) {
            return false;
        }

        DB::transaction(function () use ($reason) {
            // Restore product quantities
            foreach ($this->saleItems as $item) {
                $product = $item->product;
                if ($product) {
                    $product->updateStock(
                        $product->quantity + $item->quantity,
                        'Sale Refund: ' . $this->invoice_number
                    );
                }
            }

            // Create refund transaction
            if ($this->amount_received > 0) {
                Transaction::create([
                    'sales_id' => $this->id,
                    'store_id' => $this->store_id,
                    'contact_id' => $this->contact_id,
                    'transaction_date' => now(),
                    'amount' => -$this->amount_received,
                    'payment_method' => 'refund',
                    'transaction_type' => 'refund',
                    'note' => $reason,
                    'created_by' => auth()->id(),
                ]);
            }

            // Update sale status
            $this->update([
                'status' => self::STATUS_REFUNDED,
                'staff_note' => ($this->staff_note ?? '') . "\n\nRefunded: {$reason}",
            ]);

            // Remove loyalty points if earned
            if ($this->loyalty_points_earned > 0 && $this->contact) {
                $this->contact->redeemLoyaltyPoints(
                    $this->loyalty_points_earned,
                    "Sale refund: {$this->invoice_number}"
                );
            }
        });

        return true;
    }

    public function getReceiptData()
    {
        return [
            'invoice_number' => $this->invoice_number,
            'sale_date' => $this->sale_date->format('Y-m-d H:i:s'),
            'customer' => $this->contact?->name ?? 'Walk-in Customer',
            'items' => $this->saleItems->map(function ($item) {
                return [
                    'name' => $item->product?->name ?? $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => number_format($item->unit_price, 2),
                    'total' => number_format($item->total_price, 2),
                ];
            }),
            'subtotal' => number_format($this->total_amount, 2),
            'discount' => number_format($this->discount, 2),
            'tax' => number_format($this->tax_amount, 2),
            'total' => number_format($this->grand_total, 2),
            'amount_paid' => number_format($this->amount_received, 2),
            'balance' => number_format($this->balance, 2),
            'payment_method' => $this->transactions->pluck('payment_method')->implode(', '),
            'store' => $this->store?->name,
        ];
    }
}
