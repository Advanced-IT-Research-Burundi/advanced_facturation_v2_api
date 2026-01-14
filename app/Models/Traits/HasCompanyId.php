<?php

namespace App\Models\Traits;

trait HasCompanyId
{
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    public function scopeCompany($query, $company)
    {
        return $query->where('company_id', $company);
    }
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->company_id = auth()->user()->company_id ?? 1;
        });
    }
}