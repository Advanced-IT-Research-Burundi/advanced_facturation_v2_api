<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
    /**
     * Télécharger le template Excel pour l'import de produits
     */
    public function downloadProductTemplate()
    {
        $filename = 'modele_import_produits_'.date('Y_m_d').'.xlsx';

        return Excel::download(new ProductExport, $filename);
    }

    /**
     * Preview des données à importer (sans enregistrer)
     */
    public function previewProductImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $import = new ProductImport(true); // Mode preview
            Excel::import($import, $request->file('file'));

            $previewData = $import->getPreviewData();

            // Le code fourni dans le fichier est l'identifiant unique du produit.
            // Il est aussi utilisé pour détecter les doublons dans le fichier.
            $importedCodes = [];
            foreach ($previewData as &$item) {
                $code = trim((string) ($item['code_product'] ?? ''));
                $normalizedCode = mb_strtolower($code);
                $exists = $code !== '' && Product::withoutGlobalScopes()
                    ->where('item_code', $code)
                    ->exists();
                $duplicateInFile = $normalizedCode !== '' && isset($importedCodes[$normalizedCode]);

                $item['status'] = ($exists || $duplicateInFile) ? 'duplicate' : 'new';

                if ($normalizedCode !== '') {
                    $importedCodes[$normalizedCode] = true;
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($previewData).' lignes détectées',
                'data' => [
                    'items' => $previewData,
                    'total' => count($previewData),
                    'new_count' => collect($previewData)->where('status', 'new')->count(),
                    'duplicate_count' => collect($previewData)->where('status', 'duplicate')->count(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la lecture du fichier',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Importer les produits depuis un fichier Excel
     */
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'skip_duplicates' => 'boolean',
        ]);

        try {
            $import = new ProductImport(false);
            Excel::import($import, $request->file('file'));

            $results = $import->getResults();

            $successCount = count($results['success']);
            $errorCount = count($results['errors']);
            $duplicateCount = count($results['duplicates']);

            return response()->json([
                'success' => true,
                'message' => "{$successCount} produits importés avec succès",
                'data' => [
                    'imported' => $results['success'],
                    'errors' => $results['errors'],
                    'duplicates' => $results['duplicates'],
                    'summary' => [
                        'total_processed' => $successCount + $errorCount + $duplicateCount,
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'duplicate_count' => $duplicateCount,
                    ],
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'import',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Exporter les produits vers Excel
     */
    public function exportProducts(Request $request)
    {
        $filename = 'export_produits_'.date('Y_m_d_His').'.xlsx';

        // Créer un export avec les données réelles
        return Excel::download(new ProductDataExport($request), $filename);
    }
}

/**
 * Export des produits avec données réelles
 */
class ProductDataExport implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\WithColumnWidths, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping, \Maatwebsite\Excel\Concerns\WithStyles
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = Product::with(['categoryProduct', 'productUnit']);

        // Filtres optionnels
        if ($this->request->has('category_id')) {
            $query->where('category_product_id', $this->request->category_id);
        }

        if ($this->request->has('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_designation', 'like', "%{$search}%")
                    ->orWhere('code_product', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('item_designation');
    }

    public function headings(): array
    {
        return ProductExport::$headers;
    }

    public function map($product): array
    {
        return [
            $product->code_product ?? '',
            $product->item_designation ?? '',
            $product->marque ?? '',
            $product->item_measurement_unit ?? $product->productUnit?->name ?? '',
            $product->quantite ?? 0,
            $product->quantite_alert ?? 0,
            $product->price ?? 0,
            $product->price_ttc ?? 0,
            $product->price_promo ?? 0,
            $product->vat_rate ?? 0,
            $product->categoryProduct?->name ?? '',
            $product->description ?? '',
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '10B981'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 15,
            'D' => 15,
            'E' => 12,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 12,
            'K' => 20,
            'L' => 40,
        ];
    }
}
