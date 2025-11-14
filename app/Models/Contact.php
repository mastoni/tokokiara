<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Userstamps;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'balance',
        'loyalty_points',
        'type',   // Type of contact: customer or vendor
        'whatsapp',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_number',
        'company_name',
        'credit_limit',
        'payment_terms',
        'notes',
        'is_active',
        'store_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'loyalty_points' => 'integer',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashLogs()
    {
        return $this->hasMany(CashLog::class);
    }

    public function loyaltyPointTransactions()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    // Scopes
    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeVendors($query)
    {
        return $query->where('type', 'vendor');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('company_name', 'like', "%{$term}%");
        });
    }

    // Accessors
    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance, 2);
    }

    public function getBalanceTypeAttribute()
    {
        return $this->balance >= 0 ? 'Credit' : 'Debit';
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([$this->address, $this->city, $this->state, $this->country]);
        return implode(', ', $parts);
    }

    public function getTypeLabelAttribute()
    {
        return ucfirst($this->type);
    }

    public function getTotalPurchasesAttribute()
    {
        return $this->sales()->where('status', 'completed')->sum('total_amount');
    }

    public function getAverageOrderValueAttribute()
    {
        $salesCount = $this->sales()->where('status', 'completed')->count();
        return $salesCount > 0 ? $this->total_purchases / $salesCount : 0;
    }

    // Methods
    public function isCustomer()
    {
        return $this->type === 'customer';
    }

    public function isVendor()
    {
        return $this->type === 'vendor';
    }

    public function hasOutstandingBalance()
    {
        return abs($this->balance) > 0;
    }

    public function isOverCreditLimit()
    {
        return $this->credit_limit > 0 && abs($this->balance) > $this->credit_limit;
    }

    public function updateBalance($amount, $reason = 'Sale', $userId = null)
    {
        $this->balance += $amount;
        $this->save();

        // Log the balance change
        activity()
            ->performedOn($this)
            ->causedBy($userId ?? auth()->user())
            ->withProperties([
                'amount' => $amount,
                'new_balance' => $this->balance,
                'reason' => $reason,
            ])
            ->log("Balance updated by {$amount} for {$reason}");
    }

    public function addLoyaltyPoints($points, $reason = 'Purchase')
    {
        $this->increment('loyalty_points', $points);

        LoyaltyPointTransaction::create([
            'contact_id' => $this->id,
            'points' => $points,
            'transaction_type' => 'earned',
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    public function redeemLoyaltyPoints($points, $reason = 'Redemption')
    {
        if ($this->loyalty_points >= $points) {
            $this->decrement('loyalty_points', $points);

            LoyaltyPointTransaction::create([
                'contact_id' => $this->id,
                'points' => $points,
                'transaction_type' => 'redeemed',
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            return true;
        }

        return false;
    }

    public function getPurchaseHistory($limit = 10)
    {
        return $this->sales()
            ->with('saleItems.product')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // Accessor for formatted created_at date
    public function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('Y-m-d');
    }
}
