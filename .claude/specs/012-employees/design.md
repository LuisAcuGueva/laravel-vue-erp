# 012 · employees — Design
> **Spec:** 012 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (dentro del módulo `Maintenance` del spec 011):
  - `Models/Employee.php`
  - Transversal: `app/Support/Identity/DocumentType.php` (enum de documentos de identidad compartido con proveedores y clientes)
  - `Services/EmployeeService.php`, `Services/EmployeeUserService.php`
  - `Controllers/EmployeeController.php`, `Controllers/EmployeeUserController.php`
  - `Requests/{EmployeeRequest,ListEmployeesRequest,CreateEmployeeUserRequest,LinkEmployeeUserRequest}.php`
  - `Resources/{EmployeeResource,EmployeeOptionResource}.php`
  - `Exceptions/EmployeeRuleException.php`
- **Reutilizados:** `Users/Services/UserService::create()` (spec 003) y `Maintenance/Services/UserBranchService::sync()` (spec 011).
- **Modificado:** `app/Models/User.php` → `employee(): HasOne`.
- **Frontend:** `front-vue/src/pages/maintenance/employees/index.vue`, `front-vue/src/modules/maintenance/{services/employeesApi.js, stores/employeesStore.js, components/EmployeeFormDrawer.vue, components/EmployeeAccessCard.vue}`.

## 1. Backend — Modelo y Persistencia
### `Employee`
- `HasUuids`, `SoftDeletes`, `LogsActivity` (`logAll()->logOnlyDirty()`), `HasFactory`.
- `$fillable`: `document_type`, `document_number`, `first_name`, `last_name`, `email`, `phone`, `address`, `birth_date`, `hire_date`, `position`, `area_id`, `branch_id`, `user_id`, `is_active`.
- Casts: `document_type => DocumentType::class`, `birth_date`/`hire_date => 'date'`, `is_active => 'boolean'`.
- Relaciones: `area(): BelongsTo`, `branch(): BelongsTo`, `user(): BelongsTo`.
- Accessor `fullName(): Attribute` → `trim("{$first_name} {$last_name}")`.
- Scopes: `search($term)` (first_name, last_name, document_number, concatenación nombre+apellido con `ilike`), `active()`, `forBranch(?string)`, `forArea(?string)`, `hasUser(?bool)`.

### `app/Support/Identity/DocumentType` (transversal)
`Ruc = 'RUC'`, `Dni = 'DNI'`, `Ce = 'CE'`, `Passport = 'PASSPORT'`, `Other = 'OTHER'`; `rulesFor(): array` → RUC `['digits:11', 'regex:/^(10|15|17|20)\d{9}$/']`, DNI `['digits:8']`, otros `['alpha_num','max:15']`; `forPersons(): array` → `[Dni, Ce, Passport]`. Empleados solo aceptan `forPersons()`; proveedores (spec 021) y clientes (spec 025) usan el enum completo.

## 2. Backend — Lógica de Negocio y Servicios
### `EmployeeService`
- `list(array $filters)`: `with(['area:id,name', 'branch:id,code,name', 'user:id,email,status'])`, scopes de filtro, `orderBy('last_name')->orderBy('first_name')`, `paginate`.
- `options(?string $branchId, ?string $search)`: `active()->forBranch()->search()->limit(50)`.
- `create(array $attrs): Employee`.
- `update(Employee $e, array $attrs): Employee`: en transacción; si cambian `first_name`/`last_name` y `$e->user` existe → `$e->user->update(['name' => $e->full_name])`.
- `delete(Employee $e)`: borrado lógico y `user_id = null` (libera al usuario). Los specs 026/027 agregan aquí `EmployeeRuleException` si hay documentos.

### `EmployeeUserService`
- `createUser(Employee $e, array $userAttrs, array $roles, ?array $branchIds, User $actor): Employee` — en `DB::transaction`:
  1. Si `$e->user_id` → `EmployeeRuleException('El empleado ya tiene un usuario vinculado.')`.
  2. `$user = $userService->create([...$userAttrs, 'name' => $e->full_name, 'status' => 'active'], $roles)`.
  3. `$branchIds ??= $e->branch_id ? [$e->branch_id] : []`; si no está vacío → `UserBranchService::sync($user, $branchIds, $e->branch_id ?? $branchIds[0])`.
  4. `$e->update(['user_id' => $user->id])`.
- `link(Employee $e, User $user)`: si `Employee::where('user_id', $user->id)->whereKeyNot($e->id)->first()` existe → excepción con el nombre del otro empleado; si `$e->user_id` ya existe y es otro → excepción "desvincule primero"; si no, actualiza `user_id` y `users.name`.
- `unlink(Employee $e)`: `user_id = null`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `EmployeeRequest`:
  - `document_type` required|`Rule::enum(DocumentType::class)->only(DocumentType::forPersons())`; `document_number` required + reglas de `DocumentType::from(...)->rulesFor()` + `Rule::unique('employees','document_number')->where('document_type', $type)->whereNull('deleted_at')->ignore($id)`.
  - `first_name`, `last_name` required|string|max:100; `email` nullable|email|max:150|unique (ignorando eliminados); `phone` nullable|max:30; `address` nullable|max:255; `birth_date` nullable|date|before:today; `hire_date` nullable|date; `position` nullable|max:100.
  - `area_id` nullable|uuid|exists activo; `branch_id` nullable|uuid|exists activo; `is_active` boolean.
  - `toAttributes()` con `trim` y `mb_convert_case(..., MB_CASE_TITLE)` en nombres.
- `ListEmployeesRequest`: `page`, `per_page`, `search`, `branch_id`, `area_id`, `is_active`, `has_user` (boolean).
- `CreateEmployeeUserRequest`: reutiliza las reglas de email/password/roles de `Users/Requests/UserRequest` (extraer a un trait `UserCredentialRules` si no son reutilizables directamente; leer `UserRequest` primero) + `branch_ids` nullable|array, `branch_ids.*` uuid exists activo. Incluye el `withValidator` anti-escalamiento de roles de `UserRequest`.
- `LinkEmployeeUserRequest`: `user_id` required|uuid|exists:users,id (no eliminado).
- `EmployeeResource`: `id, document_type, document_number, first_name, last_name, full_name, email, phone, address, birth_date (Y-m-d), hire_date, position, is_active, area (whenLoaded), branch (whenLoaded), user (whenLoaded → {id, email, status}), created_at, updated_at`.
- `EmployeeOptionResource`: `id, full_name, document_number, position`.
- Rutas (`v1`):
```php
// Módulo Maintenance — Empleados (Spec 012)
Route::prefix('employees')->group(function () {
    Route::get('/options', [EmployeeController::class, 'options']);
    Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
    Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
    Route::get('/{id}', [EmployeeController::class, 'show'])->whereUuid('id')->middleware('permission:employees.view');
    Route::match(['put', 'patch'], '/{id}', [EmployeeController::class, 'update'])->whereUuid('id')->middleware('permission:employees.update');
    Route::delete('/{id}', [EmployeeController::class, 'destroy'])->whereUuid('id')->middleware('permission:employees.delete');
    Route::post('/{id}/user', [EmployeeUserController::class, 'store'])->whereUuid('id')->middleware(['permission:employees.update', 'permission:users.create']);
    Route::put('/{id}/user', [EmployeeUserController::class, 'update'])->whereUuid('id')->middleware('permission:employees.update');
    Route::delete('/{id}/user', [EmployeeUserController::class, 'destroy'])->whereUuid('id')->middleware('permission:employees.update');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/employees` | `employees.view` | query `search, branch_id, area_id, is_active, has_user, page, per_page` | 200 `data: Employee[]`, `meta` | 401, 403, 422 |
| GET | `/api/v1/employees/options` | autenticado | query `branch_id?, search?` | 200 `data: EmployeeOption[]` | 401 |
| POST | `/api/v1/employees` | `employees.create` | campos de §3 | 201 `data: Employee` | 401, 403, 422 |
| GET | `/api/v1/employees/{id}` | `employees.view` | — | 200 | 401, 403, 404 |
| PUT | `/api/v1/employees/{id}` | `employees.update` | campos de §3 | 200 | 401, 403, 404, 422 |
| DELETE | `/api/v1/employees/{id}` | `employees.delete` | — | 204 | 401, 403, 404, 422 |
| POST | `/api/v1/employees/{id}/user` | `employees.update` + `users.create` | `email, password, password_confirmation, roles[], branch_ids?[]` | 201 `data: Employee` (con `user`) | 401, 403, 404, 422 |
| PUT | `/api/v1/employees/{id}/user` | `employees.update` | `{ user_id }` | 200 | 401, 403, 404, 422 |
| DELETE | `/api/v1/employees/{id}/user` | `employees.update` | — | 200 | 401, 403, 404 |

Ejemplo `POST /employees`:
```json
{ "document_type": "DNI", "document_number": "45678912", "first_name": "María", "last_name": "Quispe Rojas", "phone": "987654321", "position": "Vendedora", "area_id": "…", "branch_id": "…", "hire_date": "2026-01-15" }
```

## 5. Permisos y seguridad
- Permisos nuevos (módulo `employees`): `employees.view|create|update|delete`. `admin` todos; `manager` `view|create|update`.
- Crear acceso exige además `users.create` y aplica la regla anti-escalamiento de roles del spec 003.
- `/options` no expone email, teléfono ni dirección.

## 6. Migraciones
`back-laravel/database/migrations/2026_10_16_000006_create_employees_table.php`:
```php
Schema::create('employees', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('document_type', 10);
    $table->string('document_number', 15);
    $table->string('first_name', 100);
    $table->string('last_name', 100);
    $table->string('email', 150)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('address')->nullable();
    $table->date('birth_date')->nullable();
    $table->date('hire_date')->nullable();
    $table->string('position', 100)->nullable();
    $table->foreignUuid('area_id')->nullable()->constrained('areas')->nullOnDelete();
    $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
    $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
    $table->index('document_number');
});
SoftDeleteUniqueIndex::make('employees', ['document_type', 'document_number']);
SoftDeleteUniqueIndex::make('employees', ['email']);
SoftDeleteUniqueIndex::make('employees', ['user_id']);
```

## 7. Configuración e infraestructura
- `EmployeeFactory` con estados `withUser()`, `inactive()`.
- Sin variables de entorno nuevas.

## 8. Frontend
- **Página** `src/pages/maintenance/employees/index.vue` (`action: 'employees.view'`): `ServerDataTable` con columnas nombre completo, documento, cargo, área, sede, acceso (chip con email o "Sin acceso"), estado y acciones. Filtros: búsqueda, sede (`/branches/options`), área (`/areas/options`), estado y "con acceso".
- **`EmployeeFormDrawer.vue`**: datos personales (tipo + número de documento con máscara según tipo), contacto, laborales (cargo, área, sede, fecha de ingreso) y `VSwitch` activo. Errores con `useApiFormErrors`.
- **`EmployeeAccessCard.vue`** (dentro del drawer en modo edición): sin usuario → "Crear acceso" (sub-formulario email, contraseña, roles desde `/roles` y sedes preseleccionadas con la del empleado), visible si `can('users.create')`; "Vincular usuario existente" con `VAutocomplete` sobre `/users?search=`. Con usuario → email, chip de estado y "Desvincular" con `useConfirm`.
- **Store** `employeesStore.js` y servicio `employeesApi.js` (incluye `createUser`, `linkUser`, `unlinkUser`, `options`).
- **Menú:** agregar "Empleados" (`tabler-id-badge-2`, `action: 'employees.view'`) al grupo "Mantenimiento" del spec 011.

## 9. Testing
- `tests/Feature/Modules/Maintenance/EmployeesApiTest.php`: CRUD; documento duplicado → 422; reutilizar documento de un empleado eliminado → 201; DNI de 7 dígitos → 422; área inactiva → 422; filtros `branch_id`, `has_user`; options solo activos; update de nombres sincroniza `users.name`; delete libera `user_id`; 403 sin permiso.
- `tests/Feature/Modules/Maintenance/EmployeeUserLinkApiTest.php`: crear acceso (usuario creado con rol, sede por defecto = sede del empleado); empleado que ya tiene usuario → 422; sin `users.create` → 403; vincular usuario libre; vincular usuario de otro empleado → 422; desvincular deja el usuario intacto; un actor no admin no asigna el rol admin → 422.
- Ejecutar: `make test-filter FILTER=Employee`.

## 10. Decisiones Rechazadas
- **Solo usuarios con rol (sin módulo de empleados):** se evaluó; el usuario eligió mantener la ficha para personal sin acceso y como base de RR. HH. y vendedores.
- **Copiar email/teléfono/documento al usuario (como Multinort):** `users` no tiene esos campos y duplicarlos crea inconsistencias; solo se sincroniza `name`.
- **`position` como catálogo:** en Multinort se usaba para filtrar técnicos; sin taller basta texto libre. Se puede normalizar en un spec de RR. HH.
