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
    private static $itemCodeCounter = 0;

    public function __construct($previewMode = false)
    {
        $this->previewMode = $previewMode;
        self::$itemCodeCounter = 0;
    }

    /**
     * Traite la collection de données importées
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 car Excel commence à 1 et on a les en-têtes

            // Ignorer les lignes complètement vides
            $hasAnyValue = false;
            foreach ($row as $value) {
                if (!is_null($value) && trim((string) $value) !== '') {
                    $hasAnyValue = true;
                    break;
                }
            }
            if (!$hasAnyValue) {
                continue;
            }

            // Normaliser les clés (gérer les accents et espaces)
            $rowData = $this->normalizeRow($row);

            // Détecter le format simple (juste nom + quantité)
            $isSimpleFormat = $this->detectSimpleFormat($rowData);

            if ($isSimpleFormat) {
                $rowData = $this->convertSimpleFormat($rowData);
            }

            // Mode preview : on retourne juste les données
            if ($this->previewMode) {
                $this->previewData[] = [
                    'row' => $rowNumber,
                    'code_product' => $rowData['code_produit'] ?? '',
                    'name' => $rowData['nom_du_produit'] ?? '',
                    'brand' => $rowData['marque'] ?? '',
                    'unit' => $rowData['unite_de_mesure'] ?? 'Pièce',
                    'quantity' => $rowData['quantite'] ?? 0,
                    'alert_quantity' => $rowData['quantite_alerte'] ?? 0,
                    'purchase_price' => $rowData['prix_dachat'] ?? 0,
                    'selling_price' => $rowData['prix_de_vente'] ?? 0,
                    'vat_rate' => $rowData['taux_tva'] ?? 0,
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
            $existingProduct = Product::where(function ($query) use ($rowData) {
                    $query->where('item_designation', $rowData['nom_du_produit']);
                    if (!empty($rowData['code_produit'])) {
                        $query->orWhere('code_product', $rowData['code_produit']);
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

                // Générer item_code unique
                $itemCode = $this->generateItemCode($rowData['nom_du_produit']);

                // Créer le produit
                $product = Product::create([
                    'item_code' => $itemCode,
                    'item_designation' => $rowData['nom_du_produit'],
                    'code_product' => $rowData['code_produit'] ?? $this->generateProductCode(),
                    'marque' => $rowData['marque'] ?? null,
                    'item_measurement_unit' => $rowData['unite_de_mesure'] ?? 'Pièce',
                    'quantite' => floatval($rowData['quantite'] ?? 0),
                    'quantite_alert' => floatval($rowData['quantite_alerte'] ?? 0),
                    'price' => floatval($rowData['prix_dachat'] ?? 0),
                    'price_ttc' => floatval($rowData['prix_de_vente'] ?? 0),
                    'vat_rate' => floatval($rowData['taux_tva'] ?? 0),
                    'product_category_id' => $categoryId,
                    'product_unit_id' => $unitId,
                    'description' => $rowData['description'] ?? null,
                    'user_id' => Auth::id(),
                ]);

                $this->results['success'][] = [
                    'row' => $rowNumber,
                    'product_id' => $product->id,
                    'name' => $product->item_designation,
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
     * Détecte si c'est un format simple (nom + quantité seulement)
     */
    private function detectSimpleFormat($rowData): bool
    {
        // Si on a des colonnes numérotées (0, 1, 2...) au lieu de noms
        $hasNumericKeys = isset($rowData[0]) || isset($rowData[1]);

        // Ou si on n'a pas les colonnes standard
        $hasStandardColumns = isset($rowData['nom_du_produit']) || isset($rowData['code_produit']);

        return $hasNumericKeys && !$hasStandardColumns;
    }

    /**
     * Convertit le format simple en format standard
     */
    private function convertSimpleFormat($rowData): array
    {
        $converted = [];

        // Chercher le nom du produit (généralement en colonne C = index 2)
        $productName = null;
        $quantity = 0;

        // Parcourir les colonnes pour trouver le nom et la quantité
        foreach ($rowData as $key => $value) {
            if (is_string($value) && !empty(trim($value)) && !is_numeric($value)) {
                $productName = trim($value);
            } elseif (is_numeric($value) && $value > 0) {
                $quantity = floatval($value);
            }
        }

        $converted['nom_du_produit'] = $productName;
        $converted['quantite'] = $quantity;

        return $converted;
    }

    /**
     * Génère un item_code unique basé sur le nom
     */
    private function generateItemCode($name): string
    {
        // Prendre les 3 premières lettres du nom + timestamp
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        if (empty($prefix)) {
            $prefix = 'PRD';
        }

        // Utiliser un compteur statique + microtime pour garantir l'unicité lors de l'import en masse
        self::$itemCodeCounter++;
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uniquePart = substr(str_replace('.', '', (string) microtime(true)), -8);
            $code = $prefix . $uniquePart . str_pad(self::$itemCodeCounter, 4, '0', STR_PAD_LEFT);

            // Vérifier que le code n'existe pas déjà en base
            if (!Product::withoutGlobalScopes()->where('item_code', $code)->exists()) {
                return $code;
            }

            // Si collision, attendre un très court instant et réessayer
            usleep(100);
        }

        // Dernier recours: utiliser uniqid
        return $prefix . strtoupper(uniqid());
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
