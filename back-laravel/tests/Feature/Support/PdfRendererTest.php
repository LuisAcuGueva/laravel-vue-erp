<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        View::addLocation(base_path('tests/Fixtures/views'));
    }

    public function test_layout_prints_company_data_and_document_title(): void
    {
        $company = app(CompanySettingService::class)->update(['legal_name' => 'Distribuidora Ñandú S.A.C.', 'ruc' => '20123456789']);

        $html = view('pdf_test', ['company' => $company, 'logo_data_uri' => null, 'watermark' => 'BORRADOR'])->render();

        $this->assertStringContainsString('Distribuidora Ñandú S.A.C.', $html);
        $this->assertStringContainsString('RUC 20123456789', $html);
        $this->assertStringContainsString('PR-000001', $html);
        $this->assertStringContainsString('BORRADOR', $html);
    }

    public function test_it_renders_a_pdf_with_the_company_name(): void
    {
        app(CompanySettingService::class)->update(['legal_name' => 'Distribuidora Ñandú S.A.C.', 'ruc' => '20123456789']);

        $response = app(PdfRenderer::class)->render('pdf_test', [], ['filename' => 'prueba.pdf']);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('prueba.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_inline_option_streams_the_pdf(): void
    {
        $response = app(PdfRenderer::class)->render('pdf_test', [], ['inline' => true, 'filename' => 'prueba.pdf']);

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_inline_query_parameter_is_honored(): void
    {
        $this->app->instance('request', Request::create('/x', 'GET', ['inline' => '1']));

        $response = app(PdfRenderer::class)->render('pdf_test', []);

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_it_embeds_the_company_logo_as_data_uri(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        app(CompanySettingService::class)->update(['logo_path' => 'company/logo.png']);

        $response = app(PdfRenderer::class)->render('pdf_test', []);

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_it_renders_without_logo_and_with_watermark(): void
    {
        $response = app(PdfRenderer::class)->render('pdf_test', ['watermark' => 'BORRADOR']);

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
