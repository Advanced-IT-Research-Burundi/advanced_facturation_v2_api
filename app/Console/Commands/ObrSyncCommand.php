<?php

namespace App\Console\Commands;

use App\Models\StockMovement;
use Illuminate\Console\Command;
use App\Services\ObrService;
use App\Models\Invoice;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Syncronisa ama  invoinces

        $this->syncStocks();
         $this->syncInvoice();
       
    }

    public function syncStocks()
    {

        $stoksMouvements = StockMovement::where('obr_submission_status', '=', 'PENDING')->latest()->get();

        foreach ($stoksMouvements as $stock){
            $obrService = new ObrService();
            $result = $obrService->addStockMovement($stock);
           dump( $result );
        }
      
    }

    public function syncInvoice(){
         $invoices = Invoice::with(['company', 'invoiceItems'])
        ->where('obr_submission_status', '=', 'PENDING')
        ->latest()->get();

        foreach ($invoices as $invoice) {
            $obrService = new ObrService();
            $result = $obrService->addInvoice($invoice);
           dump( $result );
        }
    }
    
}
