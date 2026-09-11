<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class SyncMainApp{

    private const BASE_URL = 'https://api.edenmart257.com/api';

    
    public function getToken(){
        $response = Http::post( self::BASE_URL . '/login', [
            'email' => 'nijeanlionel@gmail.com',
            'password' => 'Advanced2026'
        ]);
        if($response->successful()) {
            $response = $response->json();
            return $response['data']['access_token'];
        }
        return false;
    }
    
    public function syncInvoices(){
       $invoices = $this->get('/invoices');
       if($invoices){
        dd($invoices['data'][0]);
       }    

    }

    public function get($url,$params=null){
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
        ])->get( self::BASE_URL . $url,$params);
        if($response->successful()) {
            $response = $response->json();
            return $response['data'];
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