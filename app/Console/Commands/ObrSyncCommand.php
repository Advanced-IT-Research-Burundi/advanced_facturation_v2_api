<?php

namespace App\Console\Commands;

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

     //   $this->syncInvoice();
       
    }

    public function syncStocks(){
        dd('here');
        
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
