<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Modules\Configuration\Services\CompanySettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Único punto de generación de PDF. Envuelve dompdf e inyecta los datos de la empresa y su logo.
 */
class PdfRenderer
{
    public function __construct(
        private readonly CompanySettingService $companySettings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{paper?: string|array<int, float|int>, orientation?: string, filename?: string, inline?: bool}  $options
     */
    public function render(string $view, array $data, array $options = []): Response
    {
        $company = $this->companySettings->current();

        $pdf = Pdf::loadView($view, array_merge($data, [
            'company' => $company,
            'logo_data_uri' => $this->logoDataUri($company->logo_path),
        ]))->setPaper($options['paper'] ?? 'a4', $options['orientation'] ?? 'portrait');

        $filename = $options['filename'] ?? 'documento.pdf';
        $inline = $options['inline'] ?? request()->boolean('inline');

        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }

    private function logoDataUri(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if ($path === null || $path === '' || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}
