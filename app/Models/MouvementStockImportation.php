<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MouvementStockImportation extends Model
{
    /** @use HasFactory<\Database\Factories\MouvementStockImportationFactory> */
    use HasFactory;
    use SoftDeletes;
    
    protected $fillable = [
        "warehouse_id",
        "product_id",
        "reference_dmc",
        "rubrique_tarifaire",
        "nombre_par_paquet",
        "description_paquet",
        "system_or_device_id",
        "item_code",
        "item_designation",
        "item_quantity",
        "item_measurement_unit",
        "item_cost_price",
        "item_cost_price_currency",
        "item_movement_type",
        "item_movement_invoice_ref",
        "item_movement_description",
        "item_movement_date",
        "is_sent_to_obr",
        "item_product_name",
        "obr_status",
        "obr_message",
    ];
}
