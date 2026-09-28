<?php

namespace App\Services;

use App;
use App\Models\AppConfig;
use App\Models\Invoice;
use App\Services\ObrService;


class ReviewInvoice {


    public static function review($invoiceID){
        $invoice = Invoice::find($invoiceID);
        // check Electronique Signature
        $obr = new ObrService();
        $signature = $obr->generateInvoiceIdentifier($invoice->invoice_number, $invoice->invoice_date);

        $invoice->electronic_signature = $signature;
        $invoice->tp_TIN = AppConfig::getConfigKey('OBR_NIF');
        
        $invoice->save();

        return $invoice;
    }
}