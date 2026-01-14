<?php

namespace App\Console\Commands;

use App\Models\AppConfig;
use Illuminate\Console\Command;

class SeedDefaultConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:default-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $this->info('info');
        $configs = [
            "OBR_USERNAME" => "ws400078253401046",
            "OBR_PASSWORD" => 'kN66hYB$',
            "OBR_NIF"=> "4000782534",
            "OBR_PROD_URL" => "https://ebms.obr.gov.bi:8443/ebms_api/",
            "OBR_TEST_URL" =>"https://ebms.obr.gov.bi:9443/ebms_api/",
            "OBR_MODE_TEST" => 1,
            "CAN_SYNCRONISE_TO_OBR" => 1,
        ];

        foreach ($configs as $key => $value) {
            AppConfig::updateOrCreate(['config_key'=>$key], ['value'=>$value]);
        }
        $this->info('Finish');

    }
}
