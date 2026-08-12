<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasCompanyId, HasFactory, SoftDeletes;

    private const PRIVILEGED_ROLE_NAMES = ['super_admin', 'admin'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'user_id',
        'is_server',
        'server_code',
    ];

    protected $appends = ['role_names'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'role_id' => 'integer',
            'company_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function restaurantOrders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'server_id');
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    /**
     * Relation: User belongs to many Roles
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users')
            ->withTimestamps()
            ->withPivot('id');
    }

    /**
     * Get role names as array
     */
    public function getRoleNamesAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($role)
    {
        $roles = $this->roles->pluck('name')
            ->filter()
            ->map(fn ($name) => strtolower($name));

        if (is_array($role)) {
            $roleNames = collect($role)
                ->filter()
                ->map(fn ($name) => strtolower($name));

            return $roles->intersect($roleNames)->isNotEmpty();
        }

        return $roles->contains(strtolower($role));
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole($roles)
    {
        $roleNames = collect($roles)
            ->filter()
            ->map(fn ($name) => strtolower($name));

        return $this->roles->pluck('name')
            ->filter()
            ->map(fn ($name) => strtolower($name))
            ->intersect($roleNames)
            ->isNotEmpty();
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles($roles)
    {
        return collect($roles)->every(function ($role) {
            return $this->hasRole($role);
        });
    }

    /**
     * Assign a role to user
     */
    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        if ($this->isPrivilegedRole($role)) {
            $this->roles()->detach();
            $this->roles()->attach($role->id);
            $this->load('roles');

            return $this;
        }

        if ($this->roles()->whereIn('name', self::PRIVILEGED_ROLE_NAMES)->exists()) {
            return $this;
        }

        if (! $this->roles()->whereKey($role->id)->exists()) {
            $this->roles()->attach($role->id);
            $this->load('roles');
        }

        return $this;
    }

    /**
     * Remove a role from user
     */
    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);

        return $this;
    }

    private function isPrivilegedRole(Role $role): bool
    {
        return in_array(strtolower($role->name), self::PRIVILEGED_ROLE_NAMES);
    }
}
