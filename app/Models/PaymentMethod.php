<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasCompanyId, SoftDeletes;

    protected $fillable = ['name', 'account_number', 'account_name', 'method_type', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
