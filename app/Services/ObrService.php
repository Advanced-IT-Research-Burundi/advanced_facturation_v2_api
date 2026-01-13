<?php
    
namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Http;

class ObrService{
    public $baseUrl = "";
    public function __construct()
    {
     // URL si l'envirenoment est celle de Test
      $this->baseUrl = AppConfig::getConfigKey("OBR_MODE_TEST") ?  AppConfig::getConfigKey("OBR_TEST_URL") : AppConfig::getConfigKey("OBR_PROD_URL");
    }

    public function checkTin($tp_TIN){
      return  $this->post('checkTIN', ['tp_TIN'=> $tp_TIN]);
    }

    public function getToken(){
       $response = Http::post($this->baseUrl."/login", [
        "username"=> AppConfig::getConfigKey("OBR_USERNAME") ,
        "password"=> AppConfig::getConfigKey("OBR_PASSWORD"),
        ]);

        if ($response->json()['success']) {
            return $response->json()['result']['token'] ?? null;
        } 
        return $response; 
    }

    private function post($url , $data){
        $token = $this->getToken();
        return Http::withToken($token)->post($this->baseUrl .'/'. $url, $data);
        
    }
}