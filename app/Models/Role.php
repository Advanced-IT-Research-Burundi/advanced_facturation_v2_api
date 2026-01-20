<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'label',
        'description',
        'permissions'
    ];

    protected $casts = [
        'permissions' => 'array'
    ];

    /**
     * Relation: Role belongs to many Users
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_users')
                    ->withTimestamps()
                    ->withPivot('id');
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if role has any of the given permissions
     */
    public function hasAnyPermission($permissions)
    {
        return !empty(array_intersect($permissions, $this->permissions ?? []));
    }
}
