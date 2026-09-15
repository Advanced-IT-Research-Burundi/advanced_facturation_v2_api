<?php
namespace App\Services;
use App\Models\Customer;
use Exception;
use Illuminate\Support\Facades\Http;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TruckSyncroniser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Syncronisation\InvoinceSyncronisation;



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
        $invoinceSyncronisation = new InvoinceSyncronisation();
        $invoinceSyncronisation->syncInvoices();
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