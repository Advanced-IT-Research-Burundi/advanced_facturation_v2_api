<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TruckSyncroniser extends Model
{
    /** @use HasFactory<\Database\Factories\TruckSyncroniserFactory> */
    use HasFactory;

    protected $fillable = [
        'model_name',
        'last_id',
        'log',
        'status',
    ];
}
