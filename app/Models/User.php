<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, AuthenticationLoggable, HasRoles;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_name',
        'user_role',
        'is_active',
        'store_id',
        'phone',
        'address',
        'avatar_url',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_CASHIER = 'cashier';
    const ROLE_USER = 'user';

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'created_by');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function authenticationLogs()
    {
        return $this->morphMany(\Rappasoft\LaravelAuthenticationLog\AuthenticationLog::class, 'authenticatable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('user_role', $role);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    // Accessors
    public function getRoleLabelAttribute()
    {
        return ucfirst($this->user_role);
    }

    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getAvatarUrlAttribute($value)
    {
        return $value ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    // Methods
    public function isAdmin()
    {
        return $this->user_role === self::ROLE_ADMIN;
    }

    public function isManager()
    {
        return $this->user_role === self::ROLE_MANAGER;
    }

    public function isCashier()
    {
        return $this->user_role === self::ROLE_CASHIER;
    }

    public function canManageStore($storeId)
    {
        return $this->isAdmin() || $this->store_id === $storeId;
    }

    public function canAccessPOS()
    {
        return in_array($this->user_role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_CASHIER]);
    }

    public function canManageInventory()
    {
        return in_array($this->user_role, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    public function canViewReports()
    {
        return in_array($this->user_role, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }
}
