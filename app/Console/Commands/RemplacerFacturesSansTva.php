<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ReviewInvoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RemplacerFacturesSansTva extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remplacer-factures-sans-tva
        {--invoice=* : Numéro(s) de facture à traiter (ex: --invoice=000630), même si la TVA a déjà été corrigée en local}
        {--company= : Limiter à une entreprise}
        {--limit= : Nombre maximum de factures à traiter}
        {--taux=18 : Taux de TVA à appliquer}
        {--motif= : Motif d\'annulation envoyé à l\'OBR (par défaut : ReviewInvoice::MOTIF_REMPLACEMENT_TVA)}
        {--dry-run : Affiche les factures et les montants sans rien modifier}
        {--force : Ne pas demander de confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Annule chez l'OBR les factures envoyées sans TVA et les recrée avec la TVA (même date, même total TTC)";

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $motif = $this->option('motif') ?: ReviewInvoice::MOTIF_REMPLACEMENT_TVA;
        $taux = (float) $this->option('taux');
        $factures = ReviewInvoice::facturesARemplacer($this->option('invoice'), $motif)
            ->when($this->option('company'), fn (Builder $q, $companyId) => $q->where('company_id', $companyId))
            ->when($this->option('limit'), fn (Builder $q, $limit) => $q->limit((int) $limit))
            ->get();

        if ($factures->isEmpty()) {
            $this->info('Aucune facture à remplacer.');

            return self::SUCCESS;
        }

        $this->table(
            ['N°', 'Date', 'Total TTC', 'HTVA', 'TVA', 'Statut'],
            $factures->map(function (Invoice $facture) use ($taux) {
                $totalTTC = (float) $facture->invoice_total_amount;
                $htva = round($totalTTC / (1 + $taux / 100), 2);

                return [
                    $facture->invoice_number,
                    $facture->invoice_date,
                    number_format($totalTTC, 2, '.', ' '),
                    number_format($htva, 2, '.', ' '),
                    number_format($totalTTC - $htva, 2, '.', ' '),
                    $facture->is_cancelled ? 'Déjà annulée, à recréer' : 'À annuler puis recréer',
                ];
            })
        );
        $this->info("{$factures->count()} facture(s), total TTC : ".number_format($factures->sum('invoice_total_amount'), 2, '.', ' '));

        if ($this->option('dry-run')) {
            $this->warn('Mode --dry-run : rien n\'a été modifié.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Annuler ces {$factures->count()} facture(s) chez l'OBR et les recréer ? (irréversible)")) {
            return self::FAILURE;
        }

        $succes = 0;
        $echecs = 0;
        foreach ($factures as $facture) {
            $resultat = ReviewInvoice::remplacerFacture($facture, $motif, $taux);
            if ($resultat['success']) {
                $succes++;
                $this->line("<info>OK</info> {$resultat['message']}");
            } else {
                $echecs++;
                $this->line("<error>ERREUR</error> {$facture->invoice_number} : {$resultat['message']}");
            }
        }

        $this->info("Terminé : {$succes} remplacée(s), {$echecs} échec(s). Lancez app:obr-sync-command pour envoyer les nouvelles factures à l'OBR.");

        return $echecs > 0 ? self::FAILURE : self::SUCCESS;
    }
}
