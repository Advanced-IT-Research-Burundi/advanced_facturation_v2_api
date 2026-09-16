<?php

namespace App\Console\Commands;
use App\Services\SyncMainApp;
use Illuminate\Console\Command;

class SyncData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-data';

    protected $aliases = ['app:sync'];

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
        $app = new SyncMainApp();
        $result = $app->syncAll();


        return self::SUCCESS;
        }
}
