<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Helpers para los tests de exportación CSV (spec 031).
 */
trait InteractsWithCsvExports
{
    /**
     * @param  list<string>  $permissions
     */
    protected function authenticateWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $user->givePermissionTo(Permission::where('slug', $slug)->firstOrFail());
        }

        $pair = app(JwtService::class)->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    /**
     * Verifica cabeceras de un CSV descargable y devuelve sus filas (la primera son los encabezados).
     *
     * @return list<list<string|null>>
     */
    protected function assertCsvDownload(TestResponse $response, string $baseName): array
    {
        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="'.preg_quote($baseName, '/').'_\d{8}_\d{4}\.csv"$/',
            (string) $response->headers->get('Content-Disposition'),
        );

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, substr($content, 3));
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
