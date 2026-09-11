<?php
namespace App\Services;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TruckSyncroniser;

class SyncMainApp{

    private const BASE_URL = 'http://127.0.0.1:8080/api';
    public function getToken(){
        $response = Http::post( self::BASE_URL . '/login', [
            'email' => 'nijeanlionel@gmail.com',
            'password' => 'Advanced2026'
        ]);
        if($response->successful()) {
            $response =  $response->json();
            return $response['data']['access_token'];
        }
        return false;
    }
    
    public function syncInvoices(){
        $maxId = TruckSyncroniser::where('model_name', 'Invoice')->latest()->first()->last_id ?? 0;
       $invoices = $this->get('/invoices_sync/'.$maxId);

       if($invoices){
            $maxInvoicesId = collect($invoices)->pluck("id")->max();
            foreach ($invoices as $invoice) {

               $c= Invoice::updateOrCreate([
                    'invoice_number'=> $invoice['invoice_number']
                ],[
                   
                    "invoice_number" => $invoice['invoice_number'],
                    "invoice_date" => $invoice['invoice_date'],
                    "invoice_type" => $invoice['invoice_type'],
                    "invoice_identifier" => $invoice['invoice_identifier'],
                    "invoice_currency" => $invoice['invoice_currency'],
                    "payment_type" => $invoice['payment_type'],
                    "payment_method_id" => $invoice['payment_method_id'],
                    "tp_type" => $invoice['tp_type'],
                    "tp_name" => $invoice['tp_name'],
                    "tp_TIN" => $invoice['tp_TIN'],
                    "tp_trade_number" => $invoice['tp_trade_number'],
                    "tp_phone_number" => $invoice['tp_phone_number'],
                    "tp_fiscal_center" => $invoice['tp_fiscal_center'],
                    "vat_taxpayer" => $invoice['vat_taxpayer'],
                    "ct_taxpayer" => $invoice['ct_taxpayer'],
                    "tl_taxpayer" => $invoice['tl_taxpayer'],
                    "customer_name" => $invoice['customer_name'],
                    "customer_TIN" => $invoice['customer_TIN'],
                    "customer_address" => $invoice['customer_address'],
                    "vat_customer_payer" => $invoice['vat_customer_payer'],
                    "invoice_amount_nvat" => $invoice['invoice_amount_nvat'],
                    "invoice_vat_amount" => $invoice['invoice_vat_amount'],
                    "invoice_total_amount" => $invoice['invoice_total_amount'],
                    "payment_status" => $invoice['payment_status'],
                    "total_paid" => $invoice['total_paid'],
                    "due_date" => $invoice['due_date'],
                    "invoice_registered_number" => $invoice['invoice_registered_number'],
                    "invoice_registered_date" => $invoice['invoice_registered_date'],
                    "electronic_signature" => $invoice['electronic_signature'],
                    "obr_submission_status" => $invoice['obr_submission_status'],
                    "obr_invoice_identifier" => $invoice['obr_invoice_identifier'],
                    "obr_invoice_registered_number" => $invoice['obr_invoice_registered_number'],
                    "obr_invoice_registered_date" => $invoice['obr_invoice_registered_date'],
                    "obr_electronic_signature" => $invoice['obr_electronic_signature'],
                    "obr_sent_at" => $invoice['obr_sent_at'],
                    "is_cancelled" => $invoice['is_cancelled'],
                    "cancelled_at" => $invoice['cancelled_at'],
                    "cancelled_by" => $invoice['cancelled_by'],
                    "cancel_reason" => $invoice['cancel_reason'],
                    "obr_response_message" => $invoice['obr_response_message'],
                    "company_id" => $invoice['company_id'],
                    "customer_id" => $invoice['customer_id'],
                    "reference_invoice_id" => $invoice['reference_invoice_id'],
                    "hotel_reservation_id" => $invoice['hotel_reservation_id'],
                    "warehouse_id" => $invoice['warehouse_id'],
                    "restaurant_table_id" => $invoice['restaurant_table_id'],
                    "server_id" => $invoice['server_id'],
                    "is_restaurant" => $invoice['is_restaurant'],
                    "restaurant_order_ids" => $invoice['restaurant_order_ids'],
                    "created_by" => $invoice['created_by'],
                    "user_id" => $invoice['user_id'],
                    "created_by_id" => $invoice['created_by_id'],
                    "created_at" => $invoice['created_at'],
                    "updated_at" => $invoice['updated_at']
                ]);              
                // Invoices Items 

                foreach($invoice['invoice_items'] as $invoiceItem){
                    InvoiceItem::create([
                        "invoice_id" => $c->id ,
                        "product_id" => $invoiceItem['product_id'] ,
                        "item_designation" => $invoiceItem['item_designation'] ,
                        "item_quantity" => $invoiceItem['item_quantity'] ,
                        "item_price" => $invoiceItem['item_price'] ,
                        "item_ct" => $invoiceItem['item_ct'] ,
                        "item_tl" => $invoiceItem['item_tl'] ,
                        "item_ott_tax" => $invoiceItem['item_ott_tax'],
                        "item_tsce_tax" => $invoiceItem['item_tsce_tax'],
                        "item_price_nvat" => $invoiceItem['item_price_nvat'],
                        "vat" => $invoiceItem['vat'],
                        "item_price_wvat" => $invoiceItem['item_price_wvat'],
                        "item_total_amount" => $invoiceItem['item_total_amount'],
                        "user_id" => $invoiceItem['user_id'] ?? null,
                        
                    ]);

                    // Client NAME
                }
               

            }
            TruckSyncroniser::updateOrCreate([
                "model_name" => "Invoice",
                "last_id" => $maxInvoicesId 
            ],[
                "model_name" => "Invoice",
                "last_id" => $maxInvoicesId
            ]);
       }    

    }

    public function get($url,$params=null){
        $currntUrl =  self::BASE_URL . $url;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
        ])->get( $currntUrl,$params);
     
        if($response->successful()) {
            $response = $response->json();
            return $response;
        }
        return false;
    }

    public function post($url,$params=null){
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
        ])->post( self::BASE_URL . $url,$params);
        if($response->successful()) {
            $response = $response->json();
            return $response['data'];
        }
        return false;
    }


}