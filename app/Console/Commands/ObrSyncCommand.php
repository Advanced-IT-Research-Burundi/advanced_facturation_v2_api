<?php

namespace App\Console\Commands;

use App\Services\ObrSynchronisation;
use Illuminate\Console\Command;

class ObrSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:obr-sync-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Envoie à l'OBR les mouvements de stock, factures et importations en attente";

    /**
     * Execute the console command.
     */
    public function handle(ObrSynchronisation $synchronisation): int
    {
        $resultats = $synchronisation->toutSynchroniser();

        foreach ($resultats['details'] as $detail) {
            $statut = $detail['success'] ? '<info>OK</info>' : '<error>ERREUR</error>';
            $this->line("{$statut} {$detail['type']} {$detail['reference']} : {$detail['message']}");
        }

        $this->table(
            ['Type', 'Total', 'Envoyés', 'Erreurs'],
            collect($resultats)->only(['stocks', 'factures', 'importations'])
                ->map(fn ($r, $type) => [$type, $r['total'], $r['success'], $r['failed']])
                ->values()
        );

        return self::SUCCESS;
    }
}
