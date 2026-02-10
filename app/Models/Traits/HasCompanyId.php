<?php

namespace App\Models\Traits;

trait HasCompanyId
{
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    protected static function bootHasCompanyId()
    {
        static::addGlobalScope(new \App\Models\Scopes\CompanyScope);

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}