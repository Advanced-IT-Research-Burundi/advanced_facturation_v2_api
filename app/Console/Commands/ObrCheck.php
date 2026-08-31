<?php

namespace App\Console\Commands;

use App\Services\ObrService;
use Illuminate\Console\Command;

class ObrCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'obr:check';

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
        $obrService = new ObrService();
        $token = $obrService->getToken();
        dd($token);
    }
}
