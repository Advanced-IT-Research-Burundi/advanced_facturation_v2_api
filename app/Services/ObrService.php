<?php

namespace App\Services;

use App\Models\AppConfig;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ObrLog;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ObrService
{
    public $baseUrl = '';

    private $systemId = '';

    public function __construct()
    {
        // URL selon l'environnement (Test ou Production)
        $this->baseUrl = AppConfig::getConfigKey('OBR_MODE_TEST')
            ? AppConfig::getConfigKey('OBR_TEST_URL')
            : AppConfig::getConfigKey('OBR_PROD_URL');

        $this->systemId = AppConfig::getConfigKey('OBR_SYSTEM_ID') ?? '';
    }

    /**
     * Authentification et obtention du token JWT
     * Le token expire après 60 secondes
     */
    public function getToken()
    {
        try {
            $response = Http::post($this->baseUrl.'/login', [
                'username' => AppConfig::getConfigKey('OBR_USERNAME'),
                'password' => AppConfig::getConfigKey('OBR_PASSWORD'),
            ]);
            
            $json = $response->json();
            if (isset($json['success']) && $json['success']) {
                return $json['result']['token'] ?? null;
            }
            Log::error('OBR Login Failed', ['response' => $json]);
            return null;
        } catch (\Exception $e) {
            Log::error('OBR Login Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Vérification du NIF (Numéro d'Identification Fiscal)
     */
    public function checkTin($tp_TIN)
    {
        $response = $this->post('checkTIN', ['tp_TIN' => $tp_TIN]);
        $json = $response->json();

        if (isset($json['success']) && $json['success']) {
            return [
                'success' => true,
                'data' => $json['result']['taxpayer'][0] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => $json['msg'] ?? 'Erreur lors de la vérification du NIF',
        ];
    }

    /**
     * Obtenir les détails d'une facture
     */
    public function getInvoice($invoiceIdentifier)
    {
        $response = $this->post('getInvoice', [
            'invoice_identifier' => $invoiceIdentifier,
        ]);

        $json = $response->json();

        if (isset($json['success']) && $json['success']) {
            return [
                'success' => true,
                'data' => $json['result']['invoices'] ?? [],
            ];
        }

        return [
            'success' => false,
            'message' => $json['msg'] ?? 'Erreur lors de la récupération de la facture',
        ];
    }

    /**
     * Ajouter une facture avec accusé de réception
     */
    public function addInvoice($invoice)
    {
        // Générer l'identifiant unique de la facture
        // Préparer les données de la facture selon le format EBMS
        $invoice->loadMissing(['company', 'customer', 'invoiceItems.product']);
        $invoiceData = $this->formatInvoiceData($invoice, $invoice->company, $invoice->electronic_signature);
        //dd( $invoiceData);
        $response = $this->post('addInvoice_confirm', $invoiceData);
        $json = $response->json();
        if (isset($json['success']) && $json['success']) {
            $this->saveLogs(ObrLog::TYPE_INVOICE, $json, true, $invoice->id, null, $invoiceData);
            $invoice->update([
                'obr_invoice_identifier' => $json['result']['invoice_identifier'] ?? null,
                'obr_invoice_registered_number' => $json['result']['invoice_registered_number'] ?? null,
                'obr_invoice_registered_date' => $json['result']['invoice_registered_date'] ?? null,
                'obr_electronic_signature' => $json['electronic_signature'] ?? null,
                'obr_submission_status' => 'ACCEPTED',
                'obr_response_message' => $json['msg'] ?? 'Facture ajoutée avec succès',
                'obr_sent_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => $json['msg'] ?? 'Facture ajoutée avec succès',
                'invoice_identifier' => $json['result']['invoice_identifier'] ?? null,
                'invoice_registered_number' => $json['result']['invoice_registered_number'] ?? null,
                'invoice_registered_date' => $json['result']['invoice_registered_date'] ?? null,
                'electronic_signature' => $json['electronic_signature'] ?? null,
                'raw_response' => $json,
            ];
        }

        if (isset($json['msg']) && $json['msg'] == 'Une facture avec le même numéro existe déjà.') {
            $invoice->update([
                'obr_submission_status' => 'SENT',
                'obr_response_message' => $json['msg'],
                'obr_sent_at' => now(),
            ]);
        } else {
            $invoice->update([
                'obr_submission_status' => 'REJECTED',
                'obr_response_message' => $json['msg'] ?? 'Erreur lors de l\'envoi de la facture',
                'obr_sent_at' => now(),
            ]);
        }
        $this->saveLogs(ObrLog::TYPE_INVOICE, $json, false, $invoice->id, null, $invoiceData);

        return [
            'success' => false,
            'message' => $json['msg'] ?? 'Erreur lors de l\'envoi de la facture',
            'invoice_identifier' => $json['result']['invoice_identifier'] ?? null,
            'raw_response' => $json,
        ];
    }

    public function sendInvoiceIfSuperAdmin(Invoice $invoice, $user = null): ?array
    {
        if (! $this->isSuperAdmin($user ?? auth()->user())) {
            return null;
        }

        return $this->addInvoice($invoice);
    }

    public function isSuperAdmin($user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->get();

        return $roles->contains(function ($role) {
            return in_array(strtolower($role->name ?? ''), ['super_admin', 'superadmin'], true)
                || strtolower($role->label ?? '') === 'super administrateur';
        });
    }

    /**
     * Annuler une facture
     */
    public function cancelInvoice($invoiceIdentifier, $motif)
    {
        $response = $this->post('cancelInvoice/', [
            'invoice_identifier' => $invoiceIdentifier,
            'cn_motif' => $motif,
        ]);

        $json = $response->json();

        if (isset($json['success']) && $json['success']) {
            return [
                'success' => true,
                'message' => $json['msg'] ?? 'Facture annulée avec succès',
                'invoice_identifier' => $invoiceIdentifier,
                'raw_response' => $json,
            ];
        }

        return [
            'success' => false,
            'message' => $json['msg'] ?? 'Erreur lors de l\'annulation de la facture',
            'invoice_identifier' => $invoiceIdentifier,
            'raw_response' => $json,
        ];
    }
    
     public function  getDmcItems($reference_dmc){
        $response = $this->post('getDmcItems/',[
            "nif" => AppConfig::getConfigKey('OBR_NIF'),
            "reference_dmc" => $reference_dmc
        ]);
        $json = $response->json();
        return $json;
    }

    /**
     * Ajouter un mouvement de stock
     */
    public function addStockMovement(StockMovement $movement)
    {
        
        $data = [
            'system_or_device_id' => $movement->system_or_device_id ?? $this->systemId,
            'item_code' => $movement->item_code,
            'item_designation' => $movement->item_designation,
            'item_quantity' => (string) $movement->item_quantity,
            'item_measurement_unit' => $movement->item_measurement_unit,
            'item_cost_price' => (string) $movement->item_purchase_or_sale_price,
            'item_cost_price_currency' => $movement->item_purchase_or_sale_currency ?? 'BIF',
            'item_movement_type' => $movement->item_movement_type,
            'item_movement_invoice_ref' => $movement->item_movement_invoice_ref ?? '',
            'item_movement_description' => $movement->item_movement_description ?? '',
            'item_movement_date' => $movement->item_movement_date->format('Y-m-d H:i:s'),
        ];
        $response = $this->post('AddStockMovement', $data);
        $json = $response->json();

        if (isset($json['success']) && $json['success']) {
            $this->saveLogs("ADD_STOCK_MOVEMENT", $json, true, null, $movement->id);

            $movement->update([
                'obr_submission_status' => 'ACCEPTED',
                'obr_response_message' => $json['msg'] ?? 'Mouvement de stock ajouté avec succès',
                'obr_sent_at' => now(),
            ]);
            return [
                'success' => true,
                'message' => $json['msg'] ?? 'Mouvement de stock ajouté avec succès',
            ];
        }
        $this->saveLogs("ADD_STOCK_MOVEMENT", $json, false, null, $movement->id);
        $movement->update([
            'obr_submission_status' => 'REJECTED',
            'obr_sent_at' => now(),
        ]);
        return [
            'success' => false,
            'message' => $json['msg'] ?? 'Erreur lors de l\'envoi du mouvement de stock',
        ];
    }

    /**
     * Envoyer plusieurs mouvements de stock
     */
    public function addStockMovements(array $movements)
    {
        $results = [];

        foreach ($movements as $movement) {
            $result = $this->addStockMovement($movement);
            $results[] = [
                'movement_id' => $movement->id,
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        return $results;
    }

    /**
     * Générer l'identifiant unique de la facture
     * Format: <NIF>/<system_id>/<YYYYMMDDHHMMSS>/<invoice_number>
     */
    public function generateInvoiceIdentifier(string $invoiceNumber, mixed $invoiceDate):string
    {
        $dateFormatted = $invoiceDate instanceof \DateTime
            ? $invoiceDate->format('YmdHis')
            : date('YmdHis', strtotime($invoiceDate));


        return sprintf(
            '%s/%s/%s/%s',
            AppConfig::getConfigKey('OBR_NIF'),
            AppConfig::getConfigKey('OBR_USERNAME'),
            $dateFormatted,
            $invoiceNumber
        );
    }

    public function saveLogs($logType, $json, $success, $invoiceId = null, $stockMovementId = null, array $requestBody = [])
    {
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        ObrLog::create([
            'log_type' => $logType,
            'success' => $success,
            'status' => $success ? ObrLog::STATUS_ACCEPTED : ObrLog::STATUS_REJECTED,
            'invoice_id' => $invoiceId,
            'stock_movement_id' => $stockMovementId,
            'invoice_identifier' => $json['result']['invoice_identifier'] ?? $invoice?->obr_invoice_identifier ?? $invoice?->electronic_signature,
            'invoice_number' => $invoice?->invoice_number,
            'obr_message' => $json['msg'] ?? null,
            'obr_response' => json_encode($json),
            'electronic_signature' => $json['electronic_signature'] ?? null,
            'invoice_registered_number' => $json['result']['invoice_registered_number'] ?? null,
            'invoice_registered_date' => $json['result']['invoice_registered_date'] ?? null,
            'request_body' => $requestBody,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Formater les données de la facture selon le format EBMS
     */
    private function formatInvoiceData(Invoice $invoice, Company $company, $invoiceIdentifier)
    {
        // Préparer les items de la facture
        $invoiceItems = [];
        foreach ($invoice->invoiceItems as $item) {
            $itemPrice = $this->resolveObrItemPrice($invoice, $item);
            $priceHtva = $itemPrice * $item->item_quantity + ($item->item_ct ?? 0);
            $vatRate = (float) ($item->vat ?? 0);
            $vatAmount = $priceHtva * ($vatRate / 100);
            $priceTvac = $priceHtva + $vatAmount;
            $totalAmount = $priceTvac + ($item->item_tl ?? 0);

            $invoiceItems[] = [
                'item_designation' => $item->item_designation ?? $item->product->item_designation ?? '',
                'item_quantity' => (string) $item->item_quantity,
                'item_price' => (string) $itemPrice,
                'item_ct' => (string) ($item->item_ct ?? 0),
                'item_tl' => (string) ($item->item_tl ?? 0),
                'item_ott_tax' => (string) ($item->item_ott_tax ?? 0),
                'item_tsce_tax' => (string) ($item->item_tsce_tax ?? 0),
                'item_price_nvat' => (string) $priceHtva,
                'vat' => (string) $vatAmount,
                'item_price_wvat' => (string) $priceTvac,
                'item_total_amount' => (string) $totalAmount,
            ];
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date instanceof \DateTime
                ? $invoice->invoice_date->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s', strtotime($invoice->invoice_date)),
            'invoice_type' => $invoice->invoice_type ?? 'FN',
            'tp_type' => $company->tp_type ?? '2',
            'tp_name' => $company->name ?? '',
            'tp_TIN' => $company->tp_TIN ?? '',
            'tp_trade_number' => $company->tp_trade_number ?? '',
            'tp_postal_number' => $company->tp_postal_number ?? '',
            'tp_phone_number' => $company->phone ?? '',
            'tp_address_province' => $company->tp_address_province ?? '',
            'tp_address_commune' => $company->tp_address_commune ?? '',
            'tp_address_quartier' => $company->tp_address_quartier ?? '',
            'tp_address_avenue' => $company->tp_address_avenue ?? '',
            'tp_address_rue' => $company->tp_address_rue ?? '',
            'tp_address_number' => $company->tp_address_number ?? '',
            'vat_taxpayer' => $company->vat_taxpayer ?? '1',
            'ct_taxpayer' => $company->ct_taxpayer ?? '0',
            'tl_taxpayer' => $company->tl_taxpayer ?? '0',
            'tp_fiscal_center' => $company->tp_fiscal_center ?? 'DMC',
            'tp_activity_sector' => $company->tp_activity_sector ?? '',
            'tp_legal_form' => $company->tp_legal_form ?? '',
            'payment_type' => $invoice->payment_type ?? '1',
            'invoice_currency' => $invoice->invoice_currency ?? 'BIF',
            'customer_name' => $invoice->customer->customer_name ?? '',
            'customer_TIN' => $invoice->customer->customer_TIN ?? '',
            'customer_address' => $invoice->customer->customer_address ?? '',
            'vat_customer_payer' => $invoice->customer->vat_customer_payer ?? '0',
            'cancelled_invoice_ref' => $invoice->cancelled_invoice_ref ?? '',
            'invoice_ref' => $invoice->invoice_ref ?? '',
            'cn_motif' => $invoice->cn_motif ?? '',
            'invoice_identifier' => $invoiceIdentifier,
            'invoice_items' => $invoiceItems,
        ];
    }

    private function resolveObrItemPrice(Invoice $invoice, $item): float
    {
        if ($item->item_price !== null) {
            return (float) $item->item_price;
        }

        $fallbackPrice = 0;

        if (! $item->product_id) {
            return $fallbackPrice;
        }

        $warehousePromoPrice = WarehouseProduct::query()
            ->where('product_id', $item->product_id)
            ->when($invoice->warehouse_id, fn ($query) => $query->where('warehouse_id', $invoice->warehouse_id))
            ->orderByRaw($invoice->warehouse_id ? 'id asc' : 'quantity desc')
            ->value('price_promo');

        if ((float) $warehousePromoPrice > 0) {
            return (float) $warehousePromoPrice;
        }

        $productPromoPrice = (float) ($item->product?->price_promo ?? 0);
        if ($productPromoPrice > 0) {
            return $productPromoPrice;
        }

        return $fallbackPrice;
    }

    /**
     * Synchroniser les mouvements de stock en attente vers OBR
     */
    public function syncPendingStockMovements()
    {
        $pendingMovements = StockMovement::where('obr_submission_status', 'PENDING')
            ->orderBy('item_movement_date', 'asc')
            ->limit(50)
            ->get();

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($pendingMovements as $movement) {
            $result = $this->addStockMovement($movement);

            if ($result['success']) {
                $movement->update([
                    'obr_submission_status' => 'ACCEPTED',
                    'obr_sent_at' => now(),
                    'obr_response_message' => $result['message'],
                ]);
                $results['success']++;
            } else {
                $movement->update([
                    'obr_submission_status' => 'REJECTED',
                    'obr_response_message' => $result['message'],
                ]);
                $results['failed']++;
                $results['errors'][] = [
                    'movement_id' => $movement->id,
                    'error' => $result['message'],
                ];
            }
        }

        return $results;
    }

    /**
     * Requête POST avec authentification
     */
    private function post($url, $data)
    {
        $token = $this->getToken();

        if (! $token) {
            return new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                    'success' => false,
                    'msg' => 'Impossible d\'obtenir le token d\'authentification OBR',
                ]))
            );
        }

        try {
            $endpoint = rtrim($this->baseUrl, '/').'/'.ltrim($url, '/');
            $response = Http::withToken($token)
                ->timeout(30)
                ->post($endpoint, $data);

            Log::info('OBR API Call', [
                'url' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('OBR API Exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                    'success' => false,
                    'msg' => 'Erreur de connexion au serveur OBR: '.$e->getMessage(),
                ]))
            );
        }
    }


   
}
