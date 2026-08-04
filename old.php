<?php

function sendToObr(int $invoiceId): array
    {
        $invoice = Invoice::with(['company', 'invoiceItems'])->findOrFail($invoiceId);
        if ($invoice->obr_submission_status === 'ACCEPTED') {
            return [
                'success' => false,
                'message' => 'Cette facture a déjà été envoyée à OBR',
            ];
        }

        $result = $this->obrService->addInvoice($invoice, $invoice->company);

        if ($result['success']) {
            $invoice->update([
                'obr_submission_status' => 'ACCEPTED',
                'obr_invoice_identifier' => $result['invoice_identifier'] ?? null,
                'obr_invoice_registered_number' => $result['invoice_registered_number'] ?? null,
                'obr_invoice_registered_date' => $result['invoice_registered_date'] ?? null,
                'obr_electronic_signature' => $result['electronic_signature'] ?? null,
                'obr_sent_at' => now(),
                'obr_response_message' => $result['message'] ?? null,
            ]);
        } else {
            $invoice->update([
                'obr_submission_status' => 'REJECTED',
                'obr_response_message' => $result['message'] ?? 'Erreur inconnue',
            ]);
        }

        return $result;
    }
