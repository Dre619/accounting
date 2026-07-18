<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders document Blade views to PDF server-side (dompdf), so printing no longer
 * depends on the browser's print dialog. Shared by invoices, bills and payslips.
 */
class DocumentPdfService
{
    /**
     * Render a view to a PDF and stream it inline (opens in the browser's PDF
     * viewer rather than forcing a download).
     */
    public function streamInline(string $view, array $data, string $filename): Response
    {
        return $this->make($view, $data)->stream($filename, ['Attachment' => false]);
    }

    /**
     * Return the raw PDF bytes — handy for emailing or storing a copy.
     */
    public function raw(string $view, array $data): string
    {
        return $this->make($view, $data)->output();
    }

    private function make(string $view, array $data)
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4')
            // DejaVu Sans is bundled with dompdf and covers the glyphs the
            // templates use (em dash, minus sign, middle dot). Remote fetching
            // stays off — the logo is embedded as a data URI instead.
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
            ]);
    }

    /**
     * Turn a stored company logo into a base64 data URI dompdf can embed without
     * a network request. Returns null when there is no usable logo.
     */
    public function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }
}
