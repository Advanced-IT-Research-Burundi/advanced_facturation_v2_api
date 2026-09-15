<?php

namespace App\Console\Commands;

use App\Services\ObrService;
use Illuminate\Console\Command;
use App\Services\SyncMainApp;

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
        $SyncMainApp = new SyncMainApp();
        $token = $SyncMainApp->getToken();
        dd($token);
    }
}
