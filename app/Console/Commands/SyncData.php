<?php

namespace App\Console\Commands;

use App\Services\SyncMainApp;
use Http;
use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Services\ReviewInvoice;


class SyncData extends Command
{
    protected $signature = 'app:sync-data';

    protected $aliases = ['app:sync'];

    protected $description = 'Synchronise les données depuis le serveur principal';

    public function handle(): int
    {
        $this->info('=== Démarrage de la synchronisation ===');
        $this->newLine();
        // $this->corrigeFacture();
        $app = new SyncMainApp($this->output);
        $result = $app->syncAll();
        $this->newLine();
        $this->info('=== Synchronisation terminée ===');

        return self::SUCCESS;
    }

     public function corrigeFacture() {
        $invoinces = Invoice::all();
        foreach ($invoinces as $invoice) {
          $v =  ReviewInvoice::review($invoice->id);
          //  dump($v->id);
        }
    }
}
