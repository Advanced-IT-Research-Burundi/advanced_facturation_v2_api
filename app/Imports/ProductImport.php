<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\CategoryProduct;
use App\Models\ProductUnit;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductImport implements ToCollection, WithHeadingRow
{
    public $results = [
        'success' => [],
        'errors' => [],
        'duplicates' => [],
    ];

    public $previewMode = false;
    public $previewData = [];

    public function __construct($previewMode = false)
    {
        $this->previewMode = $previewMode;
    }

    /**
     * Traite la collection de données importées
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 car Excel commence à 1 et on a les en-têtes

            // Normaliser les clés (gérer les accents et espaces)
            $rowData = $this->normalizeRow($row);

            // Mode preview : on retourne juste les données
            if ($this->previewMode) {
                $this->previewData[] = [
                    'row' => $rowNumber,
                    'code_product' => $rowData['code_produit'] ?? '',
                    'name' => $rowData['nom_du_produit'] ?? '',
                    'brand' => $rowData['marque'] ?? '',
                    'unit' => $rowData['unite_de_mesure'] ?? '',
                    'quantity' => $rowData['quantite'] ?? 0,
                    'alert_quantity' => $rowData['quantite_alerte'] ?? 0,
                    'purchase_price' => $rowData['prix_dachat'] ?? 0,
                    'selling_price' => $rowData['prix_de_vente'] ?? 0,
                    'vat_rate' => $rowData['taux_tva'] ?? 18,
                    'category' => $rowData['categorie'] ?? '',
                    'description' => $rowData['description'] ?? '',
                    'status' => 'pending',
                ];
                continue;
            }

            // Validation basique
            if (empty($rowData['nom_du_produit'])) {
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'message' => 'Le nom du produit est obligatoire',
                    'data' => $rowData,
                ];
                continue;
            }

            // Vérifier si le produit existe déjà
            $existingProduct = Product::where('name', $rowData['nom_du_produit'])
                ->orWhere(function ($query) use ($rowData) {
                    if (!empty($rowData['code_produit'])) {
                        $query->where('code_product', $rowData['code_produit']);
                    }
                })
                ->first();

            if ($existingProduct) {
                $this->results['duplicates'][] = [
                    'row' => $rowNumber,
                    'message' => "Le produit '{$rowData['nom_du_produit']}' existe déjà",
                    'existing_id' => $existingProduct->id,
                    'data' => $rowData,
                ];
                continue;
            }

            try {
                // Trouver ou créer la catégorie
                $categoryId = null;
                if (!empty($rowData['categorie'])) {
                    $category = CategoryProduct::firstOrCreate(
                        ['name' => $rowData['categorie']],
                        ['user_id' => Auth::id()]
                    );
                    $categoryId = $category->id;
                }

                // Trouver ou créer l'unité
                $unitId = null;
                if (!empty($rowData['unite_de_mesure'])) {
                    $unit = ProductUnit::firstOrCreate(
                        ['name' => $rowData['unite_de_mesure']],
                        ['abbreviation' => Str::upper(Str::substr($rowData['unite_de_mesure'], 0, 3))]
                    );
                    $unitId = $unit->id;
                }

                // Créer le produit
                $product = Product::create([
                    'code_product' => $rowData['code_produit'] ?? $this->generateProductCode(),
                    'name' => $rowData['nom_du_produit'],
                    'brand' => $rowData['marque'] ?? null,
                    'item_measurement_unit' => $rowData['unite_de_mesure'] ?? 'Pièce',
                    'quantity' => floatval($rowData['quantite'] ?? 0),
                    'alert_quantity' => floatval($rowData['quantite_alerte'] ?? 0),
                    'purchase_price' => floatval($rowData['prix_dachat'] ?? 0),
                    'selling_price' => floatval($rowData['prix_de_vente'] ?? 0),
                    'vat_rate' => floatval($rowData['taux_tva'] ?? 18),
                    'category_product_id' => $categoryId,
                    'product_unit_id' => $unitId,
                    'description' => $rowData['description'] ?? null,
                    'user_id' => Auth::id(),
                ]);

                $this->results['success'][] = [
                    'row' => $rowNumber,
                    'product_id' => $product->id,
                    'name' => $product->name,
                ];
            } catch (\Exception $e) {
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                    'data' => $rowData,
                ];
            }
        }
    }

    /**
     * Normalise les clés de la ligne (retire accents, espaces)
     */
    private function normalizeRow($row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeKey($key);
            $normalized[$normalizedKey] = $value;
        }
        return $normalized;
    }

    /**
     * Normalise une clé
     */
    private function normalizeKey($key): string
    {
        // Convertir en minuscules
        $key = Str::lower($key);
        // Remplacer les espaces par des underscores
        $key = str_replace(' ', '_', $key);
        // Retirer les accents
        $key = $this->removeAccents($key);
        // Retirer les apostrophes
        $key = str_replace("'", '', $key);
        return $key;
    }

    /**
     * Retire les accents d'une chaîne
     */
    private function removeAccents($string): string
    {
        $search = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'ù', 'û', 'ü', 'î', 'ï', 'ç'];
        $replace = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'o', 'o', 'u', 'u', 'u', 'i', 'i', 'c'];
        return str_replace($search, $replace, $string);
    }

    /**
     * Génère un code produit unique
     */
    private function generateProductCode(): string
    {
        $lastProduct = Product::orderBy('id', 'desc')->first();
        $nextId = $lastProduct ? $lastProduct->id + 1 : 1;
        return 'PROD' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Retourne les résultats de l'import
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Retourne les données de preview
     */
    public function getPreviewData(): array
    {
        return $this->previewData;
    }
}
