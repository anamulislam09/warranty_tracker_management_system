<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The invoice as a PDF, in the WonderTech BD design. Company details, logo and bank details come from the
 * super admin's Settings page. A paid invoice carries the watermark PAID, a cancelled one CANCELLED, everything
 * else (unpaid, partly paid, overdue) the watermark DUE.
 * Needs the package:  composer require barryvdh/laravel-dompdf
 */
class InvoicePdf
{
    public function filename(Invoice $invoice): string
    {
        return $invoice->invoice_no . '.pdf';
    }

    /** Company details for the invoice. The logo is put inside the PDF as a data URI (the PDF engine needs no web access). */
    public function brand(): array
    {
        $b = Setting::many([
            'company_name', 'company_tagline', 'company_office', 'company_address', 'company_email', 'company_website', 'company_phone',
            'invoice_subtitle', 'product_name', 'product_description', 'tax_text',
            'payment_method', 'bank_name', 'account_name', 'account_no', 'bank_branch', 'invoice_notes', 'invoice_footer',
        ]);

        $b['company_name'] = $b['company_name'] ?: (config('app.name') ?: 'Warranty Tracker');
        $b['product_name'] = $b['product_name'] ?: 'Warranty Tracker';
        $b['logo'] = null;

        $path = Setting::get('logo_path');
        if ($path && is_file(public_path($path))) {
            $file = public_path($path);
            $b['logo'] = 'data:' . (mime_content_type($file) ?: 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($file));
        }

        return $b;
    }

    private function make(Invoice $invoice)
    {
        $invoice->load(['account.owner', 'payments.receiver']);

        return Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice, 'b' => $this->brand()])->setPaper('a4');
    }

    public function output(Invoice $invoice): string
    {
        return $this->make($invoice)->output();
    }

    public function download(Invoice $invoice)
    {
        return $this->make($invoice)->download($this->filename($invoice));
    }
}
