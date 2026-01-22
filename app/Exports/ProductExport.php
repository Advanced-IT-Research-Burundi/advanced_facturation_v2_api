<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public static $headers = [
        'Code Produit',
        'Nom du Produit',
        'Marque',
        'Unité de Mesure',
        'Quantité',
        'Quantité Alerte',
        'Prix d\'Achat',
        'Prix de Vente',
        'Taux TVA (%)',
        'Catégorie',
        'Description',
    ];

    /**
     * Retourne un tableau vide pour le template
     */
    public function array(): array
    {
        // Retourner quelques lignes d'exemple
        return [
            [
                'PROD001',
                'Exemple Produit 1',
                'Marque A',
                'Pièce',
                100,
                10,
                1000,
                1500,
                18,
                'Catégorie 1',
                'Description du produit exemple',
            ],
            [
                'PROD002',
                'Exemple Produit 2',
                'Marque B',
                'Kg',
                50,
                5,
                2000,
                2800,
                18,
                'Catégorie 2',
                'Autre description',
            ],
        ];
    }

    /**
     * En-têtes du fichier Excel
     */
    public function headings(): array
    {
        return self::$headers;
    }

    /**
     * Style des cellules
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style pour la première ligne (en-têtes)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
            ],
        ];
    }

    /**
     * Largeur des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,  // Code Produit
            'B' => 30,  // Nom
            'C' => 15,  // Marque
            'D' => 15,  // Unité
            'E' => 12,  // Quantité
            'F' => 15,  // Quantité Alerte
            'G' => 15,  // Prix d'Achat
            'H' => 15,  // Prix de Vente
            'I' => 12,  // Taux TVA
            'J' => 20,  // Catégorie
            'K' => 40,  // Description
        ];
    }
}
