<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export des ventes d'une période, à raison d'une ligne par article vendu.
 */
class SalesExport extends DefaultValueBinder implements FromQuery, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles
{
    private const AMOUNT_FORMAT = '#,##0.00';

    private const PAYMENT_TYPES = [
        '1' => 'Espèces',
        '2' => 'Banque',
        '3' => 'Crédit',
        '4' => 'Mobile Money',
        'cash' => 'Espèces',
        'bank' => 'Banque',
        'credit' => 'Crédit',
        'mobile' => 'Mobile Money',
    ];

    private const INVOICE_TYPES = [
        'FN' => 'Facture Normale',
        'FC' => 'Facture à Crédit',
        'RC' => 'Reçu de Caisse',
    ];

    public function __construct(
        private string $startDate,
        private string $endDate,
        private bool $includeUnvalidated = false,
    ) {}

    public function query(): Builder
    {
        return Invoice::query()
            ->with(['customer', 'user', 'invoiceItems.product:id,item_designation'])
            ->whereHas('invoiceItems')
            // Les proformas et les avoirs ne sont pas des ventes
            ->whereIn('invoice_type', array_keys(self::INVOICE_TYPES))
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->when(! $this->includeUnvalidated, fn ($q) => $q->where('is_validated', true))
            ->whereDate('invoice_date', '>=', $this->startDate)
            ->whereDate('invoice_date', '<=', $this->endDate)
            ->orderBy('invoice_date')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'N° Facture',
            'Date',
            'Type de facture',
            'Statut',
            'Client',
            'Vendu par',
            'Mode de paiement',
            'Produit',
            'Quantité',
            'Prix unitaire HTVA',
            'Montant HTVA',
            'TVA',
            'Montant TVAC',
        ];
    }

    /**
     * @param  Invoice  $invoice
     */
    public function map($invoice): array
    {
        return $invoice->invoiceItems->map(function ($item) use ($invoice) {
            $amountHtva = round($item->item_price_nvat * $item->item_quantity, 2);

            return [
                $invoice->invoice_number,
                Date::dateTimeToExcel($invoice->invoice_date),
                self::INVOICE_TYPES[$invoice->invoice_type],
                $invoice->is_validated ? 'Validée' : 'Non validée',
                $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                $invoice->user?->name ?? 'Inconnu',
                self::PAYMENT_TYPES[$invoice->payment_type] ?? $invoice->payment_type ?? 'Espèces',
                $item->item_designation ?? $item->product?->item_designation,
                $item->item_quantity,
                $item->item_price_nvat,
                $amountHtva,
                round($item->item_total_amount - $amountHtva, 2),
                $item->item_total_amount,
            ];
        })->all();
    }

    /**
     * Les textes restent des textes : un nom de client commençant par « = »
     * ne doit pas être interprété comme une formule par Excel.
     */
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'B' => 'dd/mm/yyyy hh:mm',
            'J' => self::AMOUNT_FORMAT,
            'K' => self::AMOUNT_FORMAT,
            'L' => self::AMOUNT_FORMAT,
            'M' => self::AMOUNT_FORMAT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 18,
            'D' => 12,
            'E' => 28,
            'F' => 20,
            'G' => 18,
            'H' => 35,
            'I' => 10,
            'J' => 18,
            'K' => 16,
            'L' => 14,
            'M' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '10B981'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestDataRow();
                $totalRow = $lastRow + 1;

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:M{$lastRow}");

                $sheet->setCellValueExplicit("A{$totalRow}", 'TOTAL', DataType::TYPE_STRING);
                foreach (['K', 'L', 'M'] as $column) {
                    $sheet->setCellValueExplicit("{$column}{$totalRow}", "=SUM({$column}2:{$column}{$lastRow})", DataType::TYPE_FORMULA);
                    $sheet->getStyle("{$column}{$totalRow}")->getNumberFormat()->setFormatCode(self::AMOUNT_FORMAT);
                }
                $sheet->getStyle("A{$totalRow}:M{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}
