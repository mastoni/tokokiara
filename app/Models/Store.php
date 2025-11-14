<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Userstamps;
use Illuminate\Support\Facades\Auth;

class Store extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'address',
        'contact_number',
        'email',
        'logo_url',
        'sale_prefix',
        'current_sale_number',
        'tax_rate',
        'currency_code',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tax_rate' => 'decimal:3',
        'current_sale_number' => 'integer',
        'settings' => 'array',
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashLogs()
    {
        return $this->hasMany(CashLog::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryItemStores()
    {
        return $this->hasMany(InventoryItemStore::class);
    }

    public function cheques()
    {
        return $this->hasMany(Cheque::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCurrentUser($query)
    {
        $user = Auth::user();
        if ($user && ($user->user_role === 'admin' || $user->user_role === 'super-admin')) {
            return $query;
        }

        return $query->where('id', $user?->store_id);
    }

    // Accessors
    public function getFormattedTaxRateAttribute()
    {
        return number_format($this->tax_rate * 100, 2) . '%';
    }

    public function getNextInvoiceNumberAttribute()
    {
        $year = now()->year;
        $nextNumber = str_pad($this->current_sale_number + 1, 4, '0', STR_PAD_LEFT);
        return "{$year}/{$nextNumber}";
    }

    // Methods
    public function getSetting($key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting($key, $value)
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    public function incrementSaleNumber()
    {
        $this->increment('current_sale_number');
        return $this->current_sale_number;
    }

    public function getTodaySalesTotal()
    {
        return $this->sales()
            ->whereDate('sale_date', today())
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    public function getTodaySalesCount()
    {
        return $this->sales()
            ->whereDate('sale_date', today())
            ->where('status', 'completed')
            ->count();
    }

    public function getLowStockProductsCount()
    {
        return $this->products()
            ->where('is_stock_managed', true)
            ->whereRaw('quantity <= alert_quantity')
            ->count();
    }

    // Accessor for formatted updated_at date
    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('Y-m-d');
    }

    // Accessor for formatted created_at date
    public function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('Y-m-d');
    }
}
