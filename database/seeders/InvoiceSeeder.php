<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    public function run()
    {
        $invoices = [];
        
        $invoiceTypes = ['SALE', 'PURCHASE', 'CREDIT_NOTE', 'DEBIT_NOTE'];
        
        for ($i = 1; $i <= 15; $i++) {
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            $invoiceDate = now()->subDays(rand(1, 30));
            $companyId = $i <= 5 ? 1 : ($i <= 10 ? 2 : 3);
            $customerId = $i <= 10 ? $i : ($i - 10);
            $createdById = $i <= 10 ? $i : 1;
            
            $invoices[] = [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'invoice_type' => $invoiceTypes[rand(0, 3)],
                'invoice_identifier' => 'ID-' . uniqid(),
                'invoice_currency' => $i % 2 == 0 ? 'USD' : 'CDF',
                'tp_type' => 'SARL',
                'tp_name' => 'Company Name ' . $companyId,
                'tp_TIN' => '1234567890' . $companyId,
                'tp_trade_number' => 'RC' . str_pad($companyId, 5, '0', STR_PAD_LEFT),
                'tp_phone_number' => '+2438112233' . $companyId,
                'tp_fiscal_center' => 'Center ' . $companyId,
                'vat_taxpayer' => 'YES',
                'ct_taxpayer' => 'YES',
                'tl_taxpayer' => 'YES',
                'customer_name' => 'Customer ' . $customerId,
                'customer_TIN' => 'CUST' . str_pad($customerId, 9, '0', STR_PAD_LEFT),
                'customer_address' => 'Address ' . $customerId,
                'vat_customer_payer' => $i % 2 == 0 ? 'YES' : 'NO',
                'invoice_amount_nvat' => rand(10000, 500000),
                'invoice_vat_amount' => rand(1000, 50000),
                'invoice_total_amount' => rand(11000, 550000),
                'invoice_registered_number' => 'REG-' . uniqid(),
                'invoice_registered_date' => $invoiceDate->addHours(2),
                'electronic_signature' => 'SIG-' . uniqid(),
                'obr_submission_status' => $i <= 5 ? 'ACCEPTED' : ($i <= 10 ? 'SENT' : 'PENDING'),
                'obr_response_message' => $i <= 5 ? 'Successfully accepted by OBR' : ($i <= 10 ? 'Submitted to OBR' : 'Pending submission'),
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'created_by' => $createdById,
                'user_id' => $createdById,
                'created_by_id' => $createdById,
                'created_at' => $invoiceDate,
                'updated_at' => $invoiceDate->addDays(1),
            ];
        }

        DB::table('invoices')->insert($invoices);
    }
}
