<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * Ejemplos de uso:
     *   php artisan make:module Products
     *   php artisan make:module Products --singular=Product --migration --test --routes
     *   php artisan make:module Products --all
     *   php artisan make:module Products --uuid
     */
    protected $signature = 'make:module
                            {name : Nombre del módulo en plural o StudlyCase, ej: Products, Customers, Orders}
                            {--singular= : Nombre singular de la entidad para Model/Controller/etc. Si no se indica se infiere}
                            {--migration : Generar la migración en database/migrations/}
                            {--factory : Generar el Model Factory en database/factories/}
                            {--test : Generar el Feature Test en tests/Feature/Modules/}
                            {--routes : Registrar automáticamente las rutas API en routes/api.php}
                            {--vue : Generar página/componente CRUD inicial en front-vue/src/pages/}
                            {--uuid : Usar UUID como clave primaria en lugar de id autoincremental}
                            {--all : Generar todos los componentes (migración, factory, test, rutas y vista Vue)}
                            {--force : Sobrescribir archivos si ya existen}';

    protected $description = 'Genera la arquitectura modular MVC completa (Model, Controller, Requests, Resource, Service, Provider, Migración, Test y vista Vue opcional)';

    public function handle(): int
    {
        $rawName = (string) $this->argument('name');
        $module = Str::studly($rawName);

        $singular = $this->option('singular')
            ? Str::studly((string) $this->option('singular'))
            : Str::singular($module);

        $force = (bool) $this->option('force');
        $useUuid = (bool) $this->option('uuid');

        $generateMigration = $this->option('all') || $this->option('migration');
        $generateFactory = $this->option('all') || $this->option('factory') || $this->option('test');
        $generateTest = $this->option('all') || $this->option('test');
        $registerRoutes = $this->option('all') || $this->option('routes') || (! $this->option('migration') && ! $this->option('test') && ! $this->option('factory') && ! $this->option('vue'));
        $generateVue = $this->option('all') || $this->option('vue');

        $this->info("🚀 Generando módulo MVC: {$module} (Entidad: {$singular})...");

        $modulePath = app_path("Modules/{$module}");

        if (File::isDirectory($modulePath) && ! $force) {
            if (! $this->confirm("El directorio [app/Modules/{$module}] ya existe. ¿Deseas continuar generando los archivos faltantes?", true)) {
                $this->warn('Operación cancelada.');

                return Command::FAILURE;
            }
        }

        // 1. Crear directorios de la arquitectura modular
        $directories = [
            "{$modulePath}/Controllers",
            "{$modulePath}/Models",
            "{$modulePath}/Requests",
            "{$modulePath}/Resources",
            "{$modulePath}/Services",
            "{$modulePath}/Providers",
        ];

        foreach ($directories as $dir) {
            File::ensureDirectoryExists($dir);
        }

        // 2. Generar capas MVC y de soporte
        $this->generateModel($module, $singular, $useUuid, $force);
        $this->generateResource($module, $singular, $force);
        $this->generateRequests($module, $singular, $force);
        $this->generateService($module, $singular, $force);
        $this->generateController($module, $singular, $force);
        $this->generateProvider($module, $singular, $force);

        // 3. Registrar ServiceProvider en bootstrap/providers.php
        $this->registerProviderInBootstrap($module);

        // 4. Migración (opcional o con --all)
        if ($generateMigration) {
            $this->generateMigration($module, $singular, $useUuid);
        }

        // 5. Factory (opcional o con --all / --test)
        if ($generateFactory) {
            $this->generateFactory($module, $singular, $force);
        }

        // 6. Registro de rutas en routes/api.php y configuración en bootstrap/app.php
        if ($registerRoutes) {
            $this->ensureApiRoutingConfigured();
            $this->registerRoutesInApi($module, $singular);
        }

        // 7. Feature Test (opcional o con --all / --test)
        if ($generateTest) {
            $this->generateFeatureTest($module, $singular, $force);
        }

        // 8. Vista / Página Vue (opcional o con --all / --vue)
        if ($generateVue) {
            $this->generateVueView($module, $singular, $force);
        }

        $this->newLine();
        $this->info("✨ ¡Módulo 'app/Modules/{$module}' generado exitosamente con código listo y tipado estricto!");

        return Command::SUCCESS;
    }

    protected function generateModel(string $module, string $singular, bool $useUuid, bool $force): void
    {
        $filePath = app_path("Modules/{$module}/Models/{$singular}.php");

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Model omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $tableName = Str::snake($module);

        $uuidTraits = $useUuid
            ? "use Illuminate\Database\Eloquent\Concerns\HasUuids;\n"
            : '';

        $useUuidTrait = $useUuid
            ? "    use HasFactory, HasUuids, SoftDeletes;\n"
            : "    use HasFactory, SoftDeletes;\n";

        $keyProperties = $useUuid
            ? "\n    protected \$keyType = 'string';\n    public \$incrementing = false;\n"
            : '';

        $factoryNamespace = "Database\\Factories\\{$singular}Factory";

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Models;

use Database\Factories\\{$singular}Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
{$uuidTraits}use Illuminate\Support\Carbon;

/**
 * @property string \$id
 * @property string \$name
 * @property string|null \$description
 * @property Carbon \$created_at
 * @property Carbon \$updated_at
 * @property Carbon|null \$deleted_at
 */
class {$singular} extends Model
{
{$useUuidTrait}{$keyProperties}
    protected \$table = '{$tableName}';

    /**
     * @var list<string>
     */
    protected \$fillable = [
        'name',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<{$singular}>  \$query
     * @return Builder<{$singular}>
     */
    public function scopeSearch(Builder \$query, ?string \$term): Builder
    {
        if (\$term === null || trim(\$term) === '') {
            return \$query;
        }

        \$driver = \$query->getConnection()->getDriverName();
        \$operator = \$driver === 'pgsql' ? 'ilike' : 'like';
        \$like = '%'.addcslashes(trim(\$term), '%_\\\\').'%';

        return \$query->where(function (Builder \$inner) use (\$like, \$operator) {
            \$inner->where('name', \$operator, \$like)
                ->orWhere('description', \$operator, \$like);
        });
    }

    /**
     * Crea una nueva instancia de factory para este modelo.
     */
    protected static function newFactory(): {$singular}Factory
    {
        return {$singular}Factory::new();
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Model creado:</info> app/Modules/{$module}/Models/{$singular}.php");
    }

    protected function generateResource(string $module, string $singular, bool $force): void
    {
        $filePath = app_path("Modules/{$module}/Resources/{$singular}Resource.php");

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Resource omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $singularLower = lcfirst($singular);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Resources;

use App\Modules\\{$module}\Models\\{$singular};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin {$singular}
 */
class {$singular}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        /** @var {$singular} \${$singularLower} */
        \${$singularLower} = \$this->resource;

        return [
            'id' => \${$singularLower}->id,
            'name' => \${$singularLower}->name,
            'description' => \${$singularLower}->description,
            'created_at' => \${$singularLower}->created_at?->toIso8601String(),
            'updated_at' => \${$singularLower}->updated_at?->toIso8601String(),
        ];
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Resource creado:</info> app/Modules/{$module}/Resources/{$singular}Resource.php");
    }

    protected function generateRequests(string $module, string $singular, bool $force): void
    {
        // 1. Store / Update Request
        $reqPath = app_path("Modules/{$module}/Requests/{$singular}Request.php");
        if (! File::exists($reqPath) || $force) {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class {$singular}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Normaliza los atributos validados listos para persistir.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        \$data = \$this->validated();

        return [
            'name' => trim((string) \$data['name']),
            'description' => isset(\$data['description']) && trim((string) \$data['description']) !== ''
                ? trim((string) \$data['description'])
                : null,
        ];
    }
}
PHP;
            File::put($reqPath, $content);
            $this->line("<info>✔ Request creado:</info> app/Modules/{$module}/Requests/{$singular}Request.php");
        } else {
            $this->line("<comment>Request omitido (ya existe):</comment> {$reqPath}");
        }

        // 2. List Request (Filtros y paginación)
        $listReqPath = app_path("Modules/{$module}/Requests/List{$module}Request.php");
        if (! File::exists($listReqPath) || $force) {
            $listContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class List{$module}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
PHP;
            File::put($listReqPath, $listContent);
            $this->line("<info>✔ Request creado:</info> app/Modules/{$module}/Requests/List{$module}Request.php");
        } else {
            $this->line("<comment>Request omitido (ya existe):</comment> {$listReqPath}");
        }
    }

    protected function generateService(string $module, string $singular, bool $force): void
    {
        $filePath = app_path("Modules/{$module}/Services/{$singular}Service.php");

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Service omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $singularLower = lcfirst($singular);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Services;

use App\Modules\\{$module}\Models\\{$singular};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class {$singular}Service
{
    /**
     * @param  array<string, mixed>  \$filters
     * @return LengthAwarePaginator<int, {$singular}>
     */
    public function list(array \$filters = []): LengthAwarePaginator
    {
        \$perPage = (int) (\$filters['per_page'] ?? 15);
        \$page = (int) (\$filters['page'] ?? 1);

        return {$singular}::query()
            ->search(\$filters['search'] ?? null)
            ->orderByDesc('id')
            ->paginate(perPage: \$perPage, page: \$page);
    }

    public function findById(string \$id): ?{$singular}
    {
        return {$singular}::find(\$id);
    }

    /**
     * @param  array<string, mixed>  \$attributes
     */
    public function create(array \$attributes): {$singular}
    {
        return {$singular}::create(\$attributes);
    }

    /**
     * @param  array<string, mixed>  \$attributes
     */
    public function update({$singular} \${$singularLower}, array \$attributes): {$singular}
    {
        \${$singularLower}->update(\$attributes);

        return \${$singularLower}->fresh();
    }

    public function delete({$singular} \${$singularLower}): bool
    {
        return (bool) \${$singularLower}->delete();
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Service creado:</info> app/Modules/{$module}/Services/{$singular}Service.php");
    }

    protected function generateController(string $module, string $singular, bool $force): void
    {
        $filePath = app_path("Modules/{$module}/Controllers/{$singular}Controller.php");

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Controller omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $singularLower = lcfirst($singular);
        $serviceVar = lcfirst($singular).'Service';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\\{$module}\Models\\{$singular};
use App\Modules\\{$module}\Requests\\{$singular}Request;
use App\Modules\\{$module}\Requests\List{$module}Request;
use App\Modules\\{$module}\Resources\\{$singular}Resource;
use App\Modules\\{$module}\Services\\{$singular}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class {$singular}Controller extends Controller
{
    public function __construct(
        private readonly {$singular}Service \${$serviceVar},
    ) {}

    public function index(List{$module}Request \$request): JsonResponse
    {
        \$paginator = \$this->{$serviceVar}->list(\$request->validated());

        return response()->json([
            'data' => {$singular}Resource::collection(\$paginator->items()),
            'meta' => [
                'current_page' => \$paginator->currentPage(),
                'per_page' => \$paginator->perPage(),
                'total' => \$paginator->total(),
                'last_page' => \$paginator->lastPage(),
            ],
        ]);
    }

    public function store({$singular}Request \$request): JsonResponse
    {
        \${$singularLower} = \$this->{$serviceVar}->create(\$request->toAttributes());

        return (new {$singular}Resource(\${$singularLower}))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string \$id): JsonResponse|{$singular}Resource
    {
        \${$singularLower} = \$this->{$serviceVar}->findById(\$id);

        if (\${$singularLower} === null) {
            return response()->json([
                'message' => "{$singular} no encontrado: {\$id}",
            ], 404);
        }

        return new {$singular}Resource(\${$singularLower});
    }

    public function update(string \$id, {$singular}Request \$request): JsonResponse|{$singular}Resource
    {
        \${$singularLower} = \$this->{$serviceVar}->findById(\$id);

        if (\${$singularLower} === null) {
            return response()->json([
                'message' => "{$singular} no encontrado: {\$id}",
            ], 404);
        }

        \$updated = \$this->{$serviceVar}->update(\${$singularLower}, \$request->toAttributes());

        return new {$singular}Resource(\$updated);
    }

    public function destroy(string \$id): JsonResponse
    {
        \${$singularLower} = \$this->{$serviceVar}->findById(\$id);

        if (\${$singularLower} === null) {
            return response()->json([
                'message' => "{$singular} no encontrado: {\$id}",
            ], 404);
        }

        \$this->{$serviceVar}->delete(\${$singularLower});

        return response()->json(null, 204);
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Controller creado:</info> app/Modules/{$module}/Controllers/{$singular}Controller.php");
    }

    protected function generateProvider(string $module, string $singular, bool $force): void
    {
        $providerName = "{$module}ServiceProvider";
        $filePath = app_path("Modules/{$module}/Providers/{$providerName}.php");

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Provider omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$module}\Providers;

use App\Modules\\{$module}\Services\\{$singular}Service;
use Illuminate\Support\ServiceProvider;

class {$providerName} extends ServiceProvider
{
    /**
     * Registra los servicios del módulo.
     */
    public function register(): void
    {
        \$this->app->singleton({$singular}Service::class);
    }

    /**
     * Inicializa los servicios del módulo.
     */
    public function boot(): void
    {
        // Espacio para registrar bindings, eventos o observers específicos del módulo
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Provider creado:</info> app/Modules/{$module}/Providers/{$providerName}.php");
    }

    protected function generateMigration(string $module, string $singular, bool $useUuid): void
    {
        $tableName = Str::snake($module);
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_create_{$tableName}_table.php";
        $dir = database_path('migrations');
        File::ensureDirectoryExists($dir);
        $filePath = "{$dir}/{$fileName}";

        $idDefinition = $useUuid
            ? "\$table->uuid('id')->primary();"
            : '$table->id();';

        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            {$idDefinition}
            \$table->string('name', 150);
            \$table->text('description')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Migración creada:</info> database/migrations/{$fileName}");
    }

    protected function generateFactory(string $module, string $singular, bool $force): void
    {
        $factoryName = "{$singular}Factory";
        $dir = database_path('factories');
        File::ensureDirectoryExists($dir);
        $filePath = "{$dir}/{$factoryName}.php";

        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Factory omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\\{$module}\Models\\{$singular};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<{$singular}>
 */
class {$factoryName} extends Factory
{
    protected \$model = {$singular}::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Factory creado:</info> database/factories/{$factoryName}.php");
    }

    protected function registerProviderInBootstrap(string $module): void
    {
        $bootstrapFile = base_path('bootstrap/providers.php');
        if (! File::exists($bootstrapFile)) {
            return;
        }

        $content = File::get($bootstrapFile);
        $providerClass = "App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider";

        if (str_contains($content, $providerClass) || str_contains($content, "{$module}ServiceProvider::class")) {
            return;
        }

        // Agregar declaración 'use'
        $useStatement = "use {$providerClass};\n";
        if (! str_contains($content, $useStatement)) {
            if (preg_match('/(use App\\\\Modules\\\\[^;]+;(\r?\n))+/', $content, $matches)) {
                $content = str_replace($matches[0], $matches[0].$useStatement, $content);
            } else {
                $content = (string) preg_replace('/(<\?php\r?\n\r?\n)/', "$1{$useStatement}", $content, 1);
            }
        }

        // Registrar en el array de providers
        $classEntry = "    {$module}ServiceProvider::class,\n";
        $content = (string) preg_replace('/(\n\];)/', "\n{$classEntry}];", $content, 1);

        File::put($bootstrapFile, $content);
        $this->line('<info>✔ Provider registrado en:</info> bootstrap/providers.php');
    }

    protected function ensureApiRoutingConfigured(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');
        if (! File::exists($bootstrapApp)) {
            return;
        }

        $content = File::get($bootstrapApp);

        // Si bootstrap/app.php no tiene configurada la ruta api, la agregamos
        if (! str_contains($content, 'api:')) {
            $search = "web: __DIR__.'/../routes/web.php',";
            $replace = "web: __DIR__.'/../routes/web.php',\n        api: __DIR__.'/../routes/api.php',";
            if (str_contains($content, $search)) {
                $content = str_replace($search, $replace, $content);
                File::put($bootstrapApp, $content);
                $this->line('<info>✔ Enrutamiento API configurado en:</info> bootstrap/app.php');
            }
        }

        // Asegurar que routes/api.php exista
        $apiRoutesFile = base_path('routes/api.php');
        if (! File::exists($apiRoutesFile)) {
            $initialApiContent = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

PHP;
            File::put($apiRoutesFile, $initialApiContent);
            $this->line('<info>✔ Archivo de rutas creado:</info> routes/api.php');
        }
    }

    protected function registerRoutesInApi(string $module, string $singular): void
    {
        $apiRoutesFile = base_path('routes/api.php');
        if (! File::exists($apiRoutesFile)) {
            return;
        }

        $content = File::get($apiRoutesFile);
        $controllerClass = "App\\Modules\\{$module}\\Controllers\\{$singular}Controller";
        $uri = Str::kebab($module);

        if (str_contains($content, "'{$uri}'") || str_contains($content, "\"{$uri}\"")) {
            $this->line("<comment>Rutas en routes/api.php omitidas (ya existen referencias a '{$uri}').</comment>");

            return;
        }

        // Importación del controlador si no existe
        if (! str_contains($content, $controllerClass)) {
            $useStatement = "use {$controllerClass};\n";
            if (preg_match('/(use [^;]+;(\r?\n))+/', $content, $matches)) {
                $content = str_replace($matches[0], $matches[0].$useStatement, $content);
            } else {
                $content = preg_replace('/(<\?php\r?\n)/', "$1\n{$useStatement}", $content, 1);
            }
        }

        $routesBlock = <<<PHP

// Módulo {$module}
Route::prefix('v1/{$uri}')->group(function () {
    Route::get('/', [{$singular}Controller::class, 'index']);
    Route::post('/', [{$singular}Controller::class, 'store']);
    Route::get('/{id}', [{$singular}Controller::class, 'show']);
    Route::match(['put', 'patch'], '/{id}', [{$singular}Controller::class, 'update']);
    Route::delete('/{id}', [{$singular}Controller::class, 'destroy']);
});

PHP;

        $content .= $routesBlock;
        File::put($apiRoutesFile, $content);
        $this->line("<info>✔ Rutas CRUD registradas en:</info> routes/api.php (/api/v1/{$uri})");
    }

    protected function generateFeatureTest(string $module, string $singular, bool $force): void
    {
        $testDir = base_path("tests/Feature/Modules/{$module}");
        File::ensureDirectoryExists($testDir);

        $filePath = "{$testDir}/{$module}ApiTest.php";
        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Test omitido (ya existe):</comment> {$filePath}");

            return;
        }

        $uri = Str::kebab($module);
        $tableName = Str::snake($module);
        $singularLower = lcfirst($singular);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\\{$module};

use App\Modules\\{$module}\Models\\{$singular};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {$module}ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_{$tableName}_can_be_listed(): void
    {
        {$singular}::factory()->count(3)->create();

        \$response = \$this->getJson('/api/v1/{$uri}');

        \$response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_{$tableName}_can_be_searched(): void
    {
        {$singular}::factory()->create(['name' => 'Elemento Alfa']);
        {$singular}::factory()->create(['name' => 'Elemento Beta']);

        \$response = \$this->getJson('/api/v1/{$uri}?search=Alfa');

        \$response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Elemento Alfa');
    }

    public function test_{$tableName}_can_be_created(): void
    {
        \$payload = [
            'name' => 'Nuevo {$singular}',
            'description' => 'Descripción de prueba para {$singular}',
        ];

        \$response = \$this->postJson('/api/v1/{$uri}', \$payload);

        \$response->assertCreated()
            ->assertJsonPath('data.name', 'Nuevo {$singular}')
            ->assertJsonPath('data.description', 'Descripción de prueba para {$singular}');

        \$this->assertDatabaseHas('{$tableName}', [
            'name' => 'Nuevo {$singular}',
        ]);
    }

    public function test_{$tableName}_creation_requires_name(): void
    {
        \$response = \$this->postJson('/api/v1/{$uri}', [
            'description' => 'Sin nombre proporcionado',
        ]);

        \$response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_{$tableName}_can_be_retrieved_by_id(): void
    {
        \${$singularLower} = {$singular}::factory()->create();

        \$response = \$this->getJson("/api/v1/{$uri}/{\${$singularLower}->id}");

        \$response->assertOk()
            ->assertJsonPath('data.id', \${$singularLower}->id)
            ->assertJsonPath('data.name', \${$singularLower}->name);
    }

    public function test_{$tableName}_returns_404_when_not_found(): void
    {
        \$response = \$this->getJson("/api/v1/{$uri}/999999");

        \$response->assertNotFound();
    }

    public function test_{$tableName}_can_be_updated(): void
    {
        \${$singularLower} = {$singular}::factory()->create([
            'name' => 'Nombre Antiguo',
        ]);

        \$payload = [
            'name' => 'Nombre Modificado',
            'description' => 'Descripción actualizada',
        ];

        \$response = \$this->putJson("/api/v1/{$uri}/{\${$singularLower}->id}", \$payload);

        \$response->assertOk()
            ->assertJsonPath('data.name', 'Nombre Modificado');

        \$this->assertDatabaseHas('{$tableName}', [
            'id' => \${$singularLower}->id,
            'name' => 'Nombre Modificado',
        ]);
    }

    public function test_{$tableName}_can_be_deleted(): void
    {
        \${$singularLower} = {$singular}::factory()->create();

        \$response = \$this->deleteJson("/api/v1/{$uri}/{\${$singularLower}->id}");

        \$response->assertNoContent();

        \$this->assertSoftDeleted('{$tableName}', [
            'id' => \${$singularLower}->id,
        ]);
    }
}
PHP;

        File::put($filePath, $content);
        $this->line("<info>✔ Feature Test creado:</info> tests/Feature/Modules/{$module}/{$module}ApiTest.php");
    }

    protected function generateVueView(string $module, string $singular, bool $force): void
    {
        // Ubicación de front-vue relativa al backend
        $candidates = [
            base_path('../front-vue'),
            base_path('front-vue'),
        ];

        $frontPath = null;
        foreach ($candidates as $candidate) {
            if (File::isDirectory($candidate)) {
                $frontPath = $candidate;
                break;
            }
        }

        if ($frontPath === null) {
            $this->line('<comment>Directorio front-vue no encontrado. Se omite la vista Vue.</comment>');

            return;
        }

        $uri = Str::kebab($module);
        $viewDir = "{$frontPath}/src/pages/{$uri}";
        File::ensureDirectoryExists($viewDir);

        $filePath = "{$viewDir}/index.vue";
        if (File::exists($filePath) && ! $force) {
            $this->line("<comment>Vista Vue omitida (ya existe):</comment> {$filePath}");

            return;
        }

        $singularTitle = Str::headline($singular);
        $pluralTitle = Str::headline($module);

        $content = <<<VUE
<script setup>
import { ref, onMounted } from 'vue'

const items = ref([])
const loading = ref(false)
const search = ref('')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const selectedItem = ref(null)

const formData = ref({
  id: null,
  name: '',
  description: '',
})

const fetchItems = async () => {
  loading.value = true
  try {
    const url = search.value ? `/api/v1/{$uri}?search=\${encodeURIComponent(search.value)}` : '/api/v1/{$uri}'
    const response = await fetch(url)
    const json = await response.json()
    items.value = json.data || []
  } catch (error) {
    console.error('Error cargando {$pluralTitle}:', error)
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  selectedItem.value = null
  formData.value = { id: null, name: '', description: '' }
  isDialogVisible.value = true
}

const openEditDialog = (item) => {
  selectedItem.value = item
  formData.value = { ...item }
  isDialogVisible.value = true
}

const saveItem = async () => {
  try {
    const isEditing = Boolean(formData.value.id)
    const url = isEditing ? `/api/v1/{$uri}/\${formData.value.id}` : '/api/v1/{$uri}'
    const method = isEditing ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        name: formData.value.name,
        description: formData.value.description,
      }),
    })

    if (response.ok) {
      isDialogVisible.value = false
      await fetchItems()
    }
  } catch (error) {
    console.error('Error guardando {$singularTitle}:', error)
  }
}

const confirmDelete = (item) => {
  selectedItem.value = item
  isDeleteDialogVisible.value = true
}

const deleteItem = async () => {
  if (!selectedItem.value) return
  try {
    const response = await fetch(`/api/v1/{$uri}/\${selectedItem.value.id}`, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json' },
    })
    if (response.ok) {
      isDeleteDialogVisible.value = false
      await fetchItems()
    }
  } catch (error) {
    console.error('Error eliminando {$singularTitle}:', error)
  }
}

onMounted(() => {
  fetchItems()
})
</script>

<template>
  <div>
    <VCard title="{$pluralTitle}">
      <VCardText class="d-flex flex-wrap gap-4 justify-space-between align-center">
        <div style="inline-size: 280px;">
          <VTextField
            v-model="search"
            placeholder="Buscar {$pluralTitle}..."
            density="compact"
            prepend-inner-icon="ri-search-line"
            clearable
            @update:model-value="fetchItems"
          />
        </div>

        <VBtn
          color="primary"
          prepend-icon="ri-add-line"
          @click="openCreateDialog"
        >
          Nuevo {$singularTitle}
        </VBtn>
      </VCardText>

      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th class="text-uppercase">ID</th>
            <th class="text-uppercase">Nombre</th>
            <th class="text-uppercase">Descripción</th>
            <th class="text-uppercase text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="text-center py-4">Cargando datos...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="4" class="text-center py-4 text-muted">No se encontraron registros.</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td>#{{ item.id }}</td>
            <td class="font-weight-medium">{{ item.name }}</td>
            <td class="text-truncate" style="max-inline-size: 300px;">{{ item.description || '-' }}</td>
            <td class="text-center">
              <VBtn
                icon="ri-edit-line"
                variant="text"
                size="small"
                color="info"
                @click="openEditDialog(item)"
              />
              <VBtn
                icon="ri-delete-bin-line"
                variant="text"
                size="small"
                color="error"
                @click="confirmDelete(item)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Modal Formulario -->
    <VDialog v-model="isDialogVisible" max-width="500">
      <VCard :title="formData.id ? 'Editar {$singularTitle}' : 'Nuevo {$singularTitle}'">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="formData.name"
                label="Nombre *"
                required
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="formData.description"
                label="Descripción"
                rows="3"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="isDialogVisible = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" @click="saveItem">
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal Confirmación Eliminación -->
    <VDialog v-model="isDeleteDialogVisible" max-width="400">
      <VCard title="¿Eliminar {$singularTitle}?">
        <VCardText>
          ¿Estás seguro de que deseas eliminar a "<strong>{{ selectedItem?.name }}</strong>"? Esta acción se puede revertir mediante soft delete.
        </VCardText>
        <VCardActions class="justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="isDeleteDialogVisible = false">
            Cancelar
          </VBtn>
          <VBtn color="error" @click="deleteItem">
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
VUE;

        File::put($filePath, $content);
        $this->line("<info>✔ Vista Vue creada:</info> front-vue/src/pages/{$uri}/index.vue");
    }
}
