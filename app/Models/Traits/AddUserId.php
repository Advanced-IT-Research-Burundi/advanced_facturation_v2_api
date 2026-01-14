<?php
namespace App\Models\Traits;
    

trait AddUserId{
    
public static function boot(){
    parent::boot();
    static::creating(function($model){
        $model->user_id = auth()->id();
    });
}
}