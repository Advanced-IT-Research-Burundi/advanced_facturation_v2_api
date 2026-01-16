<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWarehouse extends Model
{
    protected $table = 'user_warehouse';

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'assigned_at',
    ];
    public function warehouse(){
        return $this->belongsTo(Warehouse::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}
