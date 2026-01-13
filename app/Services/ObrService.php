<?php
    
namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Http;

class ObrService{
    public $base_url = "";
    public function __construct()
    {
     // URL si l'envirenoment est celle de Test
      $this->base_url = AppConfig::getConfigKey("OBR_MODE_TEST") ?  AppConfig::getConfigKey("OBR_TEST_URL") : AppConfig::getConfigKey("OBR_PROD_URL");

    }

    public function getToken(){
       $response = Http::post($this->base_url."/login", [
        "username"=> AppConfig::getConfigKey("OBR_USERNAME") ,
        "password"=> AppConfig::getConfigKey("OBR_PASSWORD"),
        ]);

        if ($response->json()['success']) {
            return $response->json()['result']['token'] ?? null;
        } 
        return $response;
        
    }
}