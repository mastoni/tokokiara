<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Userstamps;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Userstamps;
    
    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'image_url',
        'unit',
        'quantity',
        'alert_quantity',
        'is_stock_managed',
        'is_active',
        'brand_id',
        'category_id',
        'discount',
        'is_featured',
        'product_type',
        'meta_data',
        'attachment_id',
        'price',
        'cost',
        'tax_rate',
        'reorder_point',
        'max_stock',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_active' => 'boolean',
        'is_stock_managed' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'quantity' => 'decimal:2',
        'alert_quantity' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'max_stock' => 'decimal:2',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= alert_quantity')
                    ->where('is_stock_managed', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2);
    }

    public function getFormattedCostAttribute()
    {
        return number_format($this->cost, 2);
    }

    public function getStockStatusAttribute()
    {
        if (!$this->is_stock_managed) {
            return 'Not Managed';
        }

        if ($this->quantity <= $this->alert_quantity) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    // Methods
    public function isInStock()
    {
        return !$this->is_stock_managed || $this->quantity > 0;
    }

    public function isLowStock()
    {
        return $this->is_stock_managed && $this->quantity <= $this->alert_quantity;
    }

    public function updateStock($quantity, $reason = 'Sale', $userId = null)
    {
        $previousQuantity = $this->quantity;
        $this->quantity = $quantity;
        $this->save();

        // Log the inventory transaction
        InventoryTransaction::create([
            'product_id' => $this->id,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $quantity,
            'change' => $quantity - $previousQuantity,
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
        ]);
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
