<?php

namespace App\Console\Commands;

use App\Services\SyncMainApp;
use Http;
use Illuminate\Console\Command;

class SyncData extends Command
{
    protected $signature = 'app:sync-data';

    protected $aliases = ['app:sync'];

    protected $description = 'Synchronise les données depuis le serveur principal';

    public function handle(): int
    {
        $this->info('=== Démarrage de la synchronisation ===');
        $this->newLine();
        $app = new SyncMainApp($this->output);
        $result = $app->syncAll();
        $this->newLine();
        $this->info('=== Synchronisation terminée ===');

        return self::SUCCESS;
    }
}
