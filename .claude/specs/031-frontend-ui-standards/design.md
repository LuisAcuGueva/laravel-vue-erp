# 031 · frontend-ui-standards — Design
> **Spec:** 031 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** 008, 030
> **Status:** Done
> **Última actualización:** 2026-10-16
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
**Frontend (principal):**

| Pieza | Ruta | Acción |
|---|---|---|
| Vuetify locale + defaults | `front-vue/src/plugins/vuetify/index.js`, `defaults.js` | modificar |
| Tema / nombre app | `front-vue/themeConfig.js`, `.env.example` | modificar |
| Navegación | `front-vue/src/navigation/vertical/index.js` | reescribir |
| Filtro ACL del menú | `front-vue/src/utils/navigation.js` (nuevo) + `layouts/components/DefaultLayoutWithVerticalNav.vue` | nuevo / modificar |
| Breadcrumb | `front-vue/src/layouts/components/PageBreadcrumbs.vue` | reescribir |
| Encabezado | `front-vue/src/components/common/PageHeader.vue` | nuevo |
| Tabla | `front-vue/src/components/common/ServerDataTable.vue` + `components/common/data-table/{DataTableToolbar,DataTableFooter,DataTableColumnMenu,RowActionButton}.vue` | reescribir / nuevo |
| Composable de listado | `front-vue/src/composables/useServerTable.js` | nuevo |
| Confirmación | `front-vue/src/composables/useConfirm.js`, `components/common/GlobalConfirmDialog.vue` | modificar |
| Toasts | `front-vue/src/composables/useNotify.js`, `components/common/GlobalSnackbar.vue`, `App.vue` | nuevo / modificar |
| Opciones compartidas | `front-vue/src/utils/options.js` | nuevo |
| Eliminar | `front-vue/src/components/dialogs/ConfirmDialog.vue` | borrar |
| Migración de páginas | `pages/{clients,users,roles,permissions,audit,configuration,profile}/index.vue`, `pages/maintenance/{branches,areas,employees}/index.vue`, `pages/second-page.vue` (borrar) | modificar |
| Migración de formularios | `modules/*/components/*FormDrawer.vue`, `CompanyForm.vue`, `ParametersTable.vue`, `RolePermissionsDialog.vue`, `profile/components/*Tab.vue`, `components/common/UbigeoSelect.vue`, `pages/login.vue` | modificar |

**Backend (exportación):**

| Pieza | Ruta |
|---|---|
| Exportador CSV genérico | `back-laravel/app/Support/Export/CsvExport.php` |
| Exportadores por módulo | `app/Modules/Clients/Exports/ClientCsvExport.php`, `app/Modules/Users/Exports/UserCsvExport.php`, `app/Modules/Maintenance/Exports/{Branch,Area,Employee}CsvExport.php` |
| Servicios | `list()` se separa en `query(array $filters): Builder` + `list()`, para que el export reutilice los mismos filtros |
| Controllers | método `export()` en `ClientController`, `UserController`, `BranchController`, `AreaController`, `EmployeeController` |
| Permisos | `database/seeders/RolePermissionSeeder.php` (`*.export`) |

## 1. Backend — Modelo y Persistencia
No aplica: no hay tablas ni columnas nuevas.

## 2. Backend — Lógica de Negocio y Servicios

### 2.1 Separar query de paginación
Cada servicio de listado (`ClientService`, `UserService`, `BranchService`, `AreaService`, `EmployeeService`) expone:
```php
public function query(array $filters = [], ?Branch $branch = null): Builder; // filtros + orden, sin paginar
public function list(array $filters = [], ...): LengthAwarePaginator      // = query(...)->paginate(...)
```
El `branch` se pasa como parámetro solo en los módulos que hoy lo usan en `index` (regla multi-branch: el servicio no lee `ActiveBranch`).

### 2.2 `App\Support\Export\CsvExport`
```php
abstract class CsvExport
{
    /** @return array<string, string> clave => encabezado en español */
    abstract protected function columns(): array;

    /** @return array<string, scalar|null> fila con las mismas claves que columns() */
    abstract protected function map(Model $row): array;

    /** Hook opcional para precargar datos por lote (ej. ubigeos) */
    protected function prepareChunk(Collection $rows): void {}

    public function download(Builder $query, string $baseName): StreamedResponse
    {
        // Content-Type: text/csv; charset=UTF-8
        // Content-Disposition: attachment; filename="{$baseName}_YYYYMMDD_HHmm.csv"  (zona America/Lima)
        // Escribe BOM "\xEF\xBB\xBF", fila de encabezados y luego
        // $query->lazy(500)->chunk(500)->each(prepareChunk + fputcsv(map)) a php://output.
        // Booleanos → "Sí"/"No"; fechas → d/m/Y H:i; null → "".
    }
}
```
- Separador `,` y comillas estándar de `fputcsv`. El BOM hace que Excel lo abra como UTF-8.
- Neutralización de *CSV injection*: si una celda empieza con `= + - @ \t \r`, se le antepone `'`.

### 2.3 Exportadores por módulo
| Export | Columnas (orden) |
|---|---|
| `BranchCsvExport` (`sedes`) | Código, Nombre, Dirección, Ubicación, Teléfono, Correo, Principal, Usuarios, Estado |
| `AreaCsvExport` (`areas`) | Código, Nombre, Descripción, Estado (según columnas reales del `AreaResource`) |
| `EmployeeCsvExport` (`empleados`) | Nombres, Documento, Cargo, Área, Sede, Teléfono, Correo, Usuario vinculado, Estado |
| `ClientCsvExport` (`clientes`) | Columnas del `ClientResource` actual |
| `UserCsvExport` (`usuarios`) | Nombre, Correo, Roles, Sedes, Estado, Último acceso |

La ubicación se resuelve con `UbigeoService::describe()` en `prepareChunk` (regla ubigeo: sin join por fila).

## 3. Backend — Presentation
- `export(List<X>Request $request): StreamedResponse` reutiliza el FormRequest del `index`; se ignoran `page` y `per_page`.
- Controller: `return (new BranchCsvExport())->download($this->branchService->query($request->validated()), 'sedes');`
- Ruta **antes** de `/{id}` para que no la capture el comodín (igual se protege con `whereUuid`):
```php
Route::get('/export', [BranchController::class, 'export'])->middleware('permission:branches.export');
```
Se mantiene el middleware `branch` donde el grupo ya lo tiene.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Query | Respuesta exitosa | Errores |
|---|---|---|---|---|---|
| GET | `/api/v1/clients/export` | `jwt`, `permission:clients.export` | filtros de `ListClientsRequest` | 200 `text/csv` (stream) | 401, 403, 422 |
| GET | `/api/v1/users/export` | `jwt`, `permission:users.export` | filtros de listado | 200 `text/csv` | 401, 403, 422 |
| GET | `/api/v1/branches/export` | `jwt`, `permission:branches.export` | `search`, `is_active` | 200 `text/csv` | 401, 403, 422 |
| GET | `/api/v1/areas/export` | `jwt`, `permission:areas.export` | filtros de `ListAreasRequest` | 200 `text/csv` | 401, 403, 422 |
| GET | `/api/v1/employees/export` | `jwt`, `branch`, `permission:employees.export` | filtros de `ListEmployeesRequest` | 200 `text/csv` | 401, 403, 404 sede, 422 |

Respuesta de ejemplo:
```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="sedes_20261016_1530.csv"

﻿Código,Nombre,Dirección,Ubicación,Teléfono,Correo,Principal,Usuarios,Estado
SEDE-01,Sede Central,Av. Principal 123,Lima / Lima / Miraflores,01-1234567,sede@empresa.com,Sí,4,Activo
```
Un 422 conserva el envelope JSON estándar (spec 005).

## 5. Permisos y seguridad
- Permisos nuevos en `RolePermissionSeeder` (con `name`, `slug`, `module` y `description` en español, como los existentes): `clients.export`, `users.export`, `branches.export`, `areas.export`, `employees.export`. Se asignan al rol administrador.
- Frontend: `can('<modulo>.export')` controla la prop `exportable` de la tabla.
- La exportación pasa por el mismo `throttle:api` del grupo.
- Ítems del menú: siguen usando `action`. Las cabeceras `heading` no llevan `action`: su visibilidad se calcula (ver §8.2).

## 6. Migraciones
No aplica.

## 7. Configuración e infraestructura
- `front-vue/.env.example`: `VITE_APP_NAME=ERP`.
- `themeConfig.js`: `app.title = import.meta.env.VITE_APP_NAME || 'ERP'`, `i18n.defaultLocale = 'es'`. `verticalNav.defaultNavItemIconProps` se mantiene solo como red de seguridad; el test exige íconos explícitos.

## 8. Frontend

### 8.1 Vuetify: locale y defaults
`plugins/vuetify/index.js`:
```js
import { es } from 'vuetify/locale'
createVuetify({ locale: { locale: 'es', fallback: 'es', messages: { es } }, ... })
```
`defaults.js` (agregar o ajustar):
```js
VSelect:       { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', noDataText: 'Sin resultados', clearIcon: 'tabler-x', menuIcon: 'tabler-chevron-down' },
VAutocomplete: { ...igual..., noDataText: 'Sin resultados' },
VCombobox:     { ...igual... },
VTextField:    { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', clearIcon: 'tabler-x' },
VTextarea:     { ...igual..., autoGrow: true, rows: 3 },
VSnackbar:     { location: 'top end', timeout: 3000 },
```
No se usa `persistent-placeholder`: así Vuetify ya muestra el `label` dentro del campo y el `placeholder` solo con foco, que es el comportamiento pedido.

### 8.2 Navegación por secciones
Estructura objetivo de `navigation/vertical/index.js`. Las filas marcadas *(futuro)* son las que agregarán los specs indicados; no se crean aquí.

| Sección (`heading`) | Ítem | Ícono | Ruta / `action` |
|---|---|---|---|
| — | Inicio | `tabler-smart-home` | `root` |
| INVENTARIO | *(futuro 013–016: Catálogo maestro, Stock, Kardex, Notas de almacén)* | — | — |
| COMERCIAL | Clientes | `tabler-users` | `clients` / `clients.view` |
| COMERCIAL | *(futuro 025–026: CRM, Cotizaciones)* | — | — |
| OPERACIONES | *(futuro 021–024: Compras ▸, Proveedores; 027–029: Ventas ▸)* | — | — |
| FINANZAS | *(futuro 017–020: Bancos ▸, Caja ▸)* | — | — |
| MANTENIMIENTO | Mantenimiento ▸ | `tabler-command` | grupo |
| | ↳ Sedes | `tabler-building-store` | `maintenance-branches` / `branches.view` |
| | ↳ Áreas | `tabler-sitemap` | `maintenance-areas` / `areas.view` |
| | ↳ Empleados | `tabler-id-badge-2` | `maintenance-employees` / `employees.view` |
| MANTENIMIENTO | Seguridad ▸ | `tabler-shield-lock` | grupo |
| | ↳ Usuarios | `tabler-user` | `users` / `users.view` |
| | ↳ Roles | `tabler-shield` | `roles` / `roles.view` |
| | ↳ Permisos | `tabler-key` | `permissions` / `permissions.view` |
| MANTENIMIENTO | Auditoría | `tabler-history` | `audit` / `audit.view` |
| MANTENIMIENTO | Configuración | `tabler-settings` | `configuration` / `configuration.view` |

Orden fijo de secciones: INVENTARIO · COMERCIAL · OPERACIONES · FINANZAS · TALLER · MANTENIMIENTO. Las secciones sin ítems no se declaran.

**Filtro de visibilidad (`utils/navigation.js`):**
```js
export const filterNavByAcl = (items, can) => { /* … */ }
// 1. Grupo: filtra children por can(action, subject); se descarta si queda vacío.
// 2. Link: se queda si can(action, subject) (sin action ⇒ visible).
// 3. Heading: se queda solo si el siguiente bloque hasta el próximo heading tiene ≥ 1 ítem visible.
export const findNavTrail = (items, routeName) => [] // [{ title, to? }] de grupos + link, sin headings
```
`DefaultLayoutWithVerticalNav.vue` pasa `computed(() => filterNavByAcl(navItems, ability.can.bind(ability)))` a `VerticalNavLayout`. Es reactivo a cambios de permisos (`useAcl`).

### 8.3 Breadcrumb y metadatos de ruta
Cada página declara en `definePage`:
```js
definePage({
  meta: {
    action: 'branches.view',
    title: 'Sedes',
    icon: 'tabler-building-store',
    description: 'Gestión de sedes físicas y sucursales de la empresa',
  },
})
```
`PageBreadcrumbs.vue`:
- **Título:** `route.meta.title`.
- **Ruta:** `[{ title: 'Inicio', to: '/' }, ...findNavTrail(nav, route.name)]`. Si la ruta no está en el menú: `[Inicio, { title: meta.title }]`. Los grupos se muestran como texto sin enlace.
- Se elimina `humanize()`.

Páginas sin entrada en el menú que también declaran `meta.title`: `profile` («Mi cuenta», `tabler-user-circle`), `[...error]` («Página no encontrada»). `login` usa layout `blank` y no la necesita.

### 8.4 `PageHeader.vue`
```vue
<PageHeader>                              <!-- usa route.meta por defecto -->
  <template #actions>…</template>         <!-- opcional -->
</PageHeader>
props: icon?, title?, description?
```
Maquetación: `VAvatar variant="tonal" color="primary" rounded size="42"` con `VIcon size="26"`, `h5.text-h5.font-weight-bold` y `p.text-body-2.text-medium-emphasis.mb-0`. Las acciones se alinean a la derecha y en móvil se apilan. Va como cabecera de la `VCard` del listado (`VCardText` superior), no como bloque aparte.

### 8.5 `ServerDataTable` (v2)
```vue
<ServerDataTable
  table-id="branches"
  :headers="headers"
  :items="store.items"
  :total-items="store.meta.total"
  :loading="store.isLoading"
  v-model:options="table.options"          <!-- { page, itemsPerPage, sortBy } -->
  v-model:search="table.search"
  search-placeholder="Código o nombre"
  create-label="Nueva sede"                <!-- tooltip del botón Nuevo y CTA del empty state -->
  :creatable="can('branches.create')"
  :exportable="can('branches.export')"
  :exporting="table.exporting"
  @refresh="table.reload"
  @create="openCreateDrawer"
  @export="table.export('/v1/branches/export', 'sedes')"
>
  <template #filters> <VSelect v-model="table.filters.is_active" :items="STATUS_OPTIONS" label="Estado" density="compact" clearable /> </template>
  <template #item.actions="{ item }">
    <RowActionButton action="edit" @click="openEditDrawer(item)" />
    <RowActionButton action="delete" :loading="deletingId === item.id" @click="remove(item)" />
  </template>
</ServerDataTable>
```
**Props:** `tableId` (req.), `headers`, `items`, `totalItems`, `loading`, `options`, `search`, `searchable` (true), `searchPlaceholder`, `createLabel` («Nuevo registro»), `creatable`, `exportable`, `exporting`, `importable`, `refreshable` (true), `columnToggle` (true), `itemsPerPageOptions` ([10, 25, 50, 100]), `showExpand`.
**Emits:** `update:options`, `update:search`, `refresh`, `create`, `export`, `import(File)`.
**Headers:** extensión opcional `{ alwaysVisible: true, hidden: true /* oculta por defecto */, maxWidth: 240 }`. El título se escribe en mayúsculas en la definición.

**Subcomponentes (`components/common/data-table/`):**
- `DataTableToolbar.vue`: fila `d-flex flex-wrap gap-4 align-center`. A la izquierda, «**{n}** registros encontrados» (`n.toLocaleString('es-PE')`; singular «1 registro encontrado»). En el centro, `VTextField` de búsqueda (`density="compact"`, `prepend-inner-icon="tabler-search"`, `clearable`, `label="Buscar"`) y el slot `filters`. A la derecha, botones `VBtn icon variant="tonal" rounded="circle" size="38"` con `VTooltip` y `aria-label`, en este orden:

  | Botón | Ícono | Color |
  |---|---|---|
  | Refrescar | `tabler-refresh` | `secondary` |
  | Exportar | `tabler-file-export` | `primary` |
  | Importar | `tabler-file-import` | `success` |
  | Nuevo | `tabler-plus` | `primary`, `variant="elevated"` |

  Después va el botón Columnas (`tabler-columns-3`, `outlined`).
- `DataTableColumnMenu.vue`: `VMenu` con `VCheckbox` por columna. La lista de ocultas se persiste en `localStorage[`dt-columns:${userId}:${tableId}`]` (`userId` del `authStore`). Incluye la acción «Restablecer».
- `DataTableFooter.vue`: a la izquierda «Mostrando {from}–{to} de {total}» (0 registros → «Sin registros»); a la derecha «Filas por página» `VSelect` compact (ancho 80) y `VPagination` (`total-visible="5"`, primera/última por defaults). `VDataTableServer` se renderiza con `hide-default-footer`.
- `RowActionButton.vue`: `props: action ('view'|'edit'|'delete'|'custom'), icon?, color?, tooltip?, loading?, disabled?`. Mapa: view → `tabler-eye`/`info`/«Ver detalle»; edit → `tabler-pencil`/`success`/«Editar»; delete → `tabler-trash`/`error`/«Eliminar». Render: `VBtn icon variant="outlined" rounded="circle" size="34"` + `VIcon size="18"` + `VTooltip` + `aria-label`. Las acciones van en un contenedor `d-flex gap-2 justify-center`.
- **Estado vacío:** `VIcon tabler-database-off size 40`, texto «No se encontraron registros» (o «No hay resultados para "<búsqueda>"» si hay búsqueda o filtros) y, si `creatable` y no hay filtros, `VBtn` con el texto de `createLabel`.
- **Loading:** se mantiene el `VSkeletonLoader table-row@5` del spec 008.

### 8.6 `useServerTable`
```js
const table = useServerTable({
  fetch: params => store.fetchBranches(params),  // recibe { page, per_page, sort_by, sort_dir, search, ...filters }
  filters: { is_active: null },
  defaultItemsPerPage: 10,
})
// expone: options, search, filters (reactive), reload(), reset(), exporting, export(url, baseName)
```
- Un `watch` de `search` y `filters` con *debounce* de 350 ms → `page = 1` → `reload()`.
- `options` → `reload()` inmediato.
- Omite parámetros `null`, `''` o `undefined`.
- `export()` llama `downloadFile(url, currentParams sin page/per_page, baseName)` y maneja `exporting` y errores (toast).
- Carga inicial en `onMounted`.

### 8.7 Confirmación: `useConfirm`
```js
confirm({ title, message, icon, color, confirmText, cancelText, hideCancel }) → Promise<boolean>
confirmDelete({ entity: 'sede', name: 'Sede Central (SEDE-01)' }) → Promise<boolean>
  // title «¿Eliminar sede?», message «Se eliminará «Sede Central (SEDE-01)». Esta acción no se puede deshacer.»
  // icon tabler-trash, color error, confirmText «Sí, eliminar»
alert({ title = 'No se puede eliminar', message, color = 'warning', icon = 'tabler-alert-circle', confirmText = 'Entendido' }) → Promise<void>
```
- Se elimina `confirmColor` (no existe): las llamadas actuales migran a `color` / `alert()`.
- `GlobalConfirmDialog`: `persistent`. Respeta `hideCancel`. El botón de confirmar recibe foco al abrir. `Esc` resuelve `false`.
- Si se llama `confirm()` con otro diálogo abierto, el anterior se resuelve con `false`.

**Patrón de eliminación estándar** (helper `useDeleteAction` dentro de `useServerTable` o en el composable propio):
```js
const remove = async item => {
  if (!await confirmDelete({ entity: 'sede', name: `${item.name} (${item.code})` })) return
  deletingId.value = item.id
  try { await store.deleteBranch(item.id); notify.success('Sede eliminada correctamente'); table.reload() }
  catch (e) { await alert({ message: e?.data?.message || 'No se pudo eliminar el registro.' }) }
  finally { deletingId.value = null }
}
```

### 8.8 Toasts: `useNotify` + `GlobalSnackbar`
- Cola reactiva de módulo (`ref([])`). `GlobalSnackbar` muestra uno a la vez con `VSnackbar` (`location="top end"`, `timeout 3000`, `variant="flat"`, color según tipo, ícono `tabler-circle-check` / `tabler-alert-circle` / `tabler-info-circle` / `tabler-alert-triangle`, y botón cerrar `tabler-x`).
- Se monta en `App.vue`, junto a `GlobalConfirmDialog`.
- Mensajes estándar: «<Entidad> creado(a) correctamente», «… actualizado(a) …», «… eliminado(a) …». Se escriben literalmente en cada página; no hay generador de género.

### 8.9 Inputs y combos
- Patrón: `<VTextField v-model="form.code" label="Código *" placeholder="SEDE-01" :error-messages="errors.code" />`.
- `UbigeoSelect`: reemplaza `AppAutocomplete` por `VAutocomplete` con `label` (props actuales sin cambio), `placeholder="Escribe un distrito…"` y `clearable`. Su test `UbigeoSelect.spec.js` se ajusta.
- `utils/options.js`:
  ```js
  export const STATUS_OPTIONS = [{ title: 'Activo', value: true }, { title: 'Inactivo', value: false }]
  ```
  El valor que se envía a la API se convierte en `useServerTable` (`true → '1'`, `false → '0'`), que es lo que esperan los `List*Request` actuales. Si algún `ListRequest` espera `active`/`inactive` (Usuarios), la página mapea en `fetch`.
- Se eliminan las opciones `{ value: null, title: 'Todos …' }`.

### 8.10 Guardrail
`src/tests/unit/uiGuardrails.spec.js` recorre con `import.meta.glob('/src/{pages,modules,components,layouts}/**/*.vue', { query: '?raw', eager: true })` y falla si encuentra:
- `<AppTextField`, `<AppSelect`, `<AppAutocomplete`, `<AppTextarea`, `<AppCombobox`;
- `<VTable` en `src/pages/**` (las tablas de detalle dentro de drawers en `modules/**` se permiten);
- `<VDataTableServer` fuera de `ServerDataTable.vue`;
- `ConfirmDialog`, `window.confirm(` o `confirmColor`;
- `items-length=`.

Excluye `src/@core/**` y `src/views/**` (código de la plantilla Vuexy).

## 9. Testing
**Backend** (`make test-filter FILTER=Export`):
- `tests/Feature/Support/CsvExportTest.php`: BOM, encabezados, booleanos «Sí»/«No», neutralización de `=cmd`, nombre de archivo con timestamp de Lima.
- `tests/Feature/Modules/Maintenance/BranchExportApiTest.php`, `AreaExportApiTest.php`, `EmployeeExportApiTest.php`, `tests/Feature/Modules/Clients/ClientExportApiTest.php`, `tests/Feature/Modules/Users/UserExportApiTest.php`. Cada uno verifica:
  - 403 sin permiso `*.export`;
  - 200 con `Content-Type` y `Content-Disposition`;
  - que los filtros `search` y `is_active` se apliquen;
  - que exporte todas las filas aunque superen `per_page`;
  - en Empleados, el alcance de sede (404 con sede no accesible).

  Los que guardan ubigeo usan `SeedsUbigeoFixture`.

**Frontend** (`npm run test:unit`):
- `navigation.spec.js`: todos los ítems no-heading tienen ícono explícito distinto de `tabler-circle`; los headings están en mayúsculas; `filterNavByAcl` oculta headings y grupos vacíos; `findNavTrail('maintenance-branches')` devuelve `[Mantenimiento, Sedes]`.
- `PageBreadcrumbs.spec.js`: ruta en menú, ruta fuera de menú y ausencia de textos del path.
- `routeMeta.spec.js`: toda ruta con `meta.action` declara `title`, `icon` y `description`.
- `useConfirm.spec.js` (ampliar): `hideCancel`, `confirmDelete` arma título y mensaje, `alert` resuelve, y un `confirm` concurrente resuelve el anterior con `false`.
- `useNotify.spec.js`: cola y tipos.
- `useServerTable.spec.js`: debounce, reset a página 1, omisión de params vacíos, `export` con params sin paginación.
- `ServerDataTable.spec.js`: contador singular/plural, footer «Mostrando 11–20 de 352», botones según props, persistencia de columnas y empty state con CTA.
- `uiGuardrails.spec.js` (§8.10).

**Manual:** temas claro y oscuro; anchos 375 px, 768 px y 1440 px; navegación solo con teclado en tabla y diálogo.

## 10. Decisiones Rechazadas
- **Mantener `AppTextField` (label encima) como estándar:** el usuario pidió el comportamiento de label flotante del form de Sede, que es el nativo de Vuetify y no necesita wrapper.
- **Crear un `AppDataTable` nuevo y dejar `ServerDataTable`:** tendríamos dos componentes de tabla. Se evoluciona `ServerDataTable` (spec 008) y se migran sus 4 usos.
- **Derivar el breadcrumb del path con un diccionario de traducciones:** se duplicarían títulos. El árbol de navegación + `meta.title` ya es la fuente única.
- **Exportar en el frontend desde los ítems cargados:** solo tendría la página visible. El export debe respetar todos los registros filtrados.
- **`.xlsx` con `maatwebsite/excel`:** agrega dependencia y memoria. El CSV con BOM cubre el caso.
- **Toasts con librería externa (`vue-toastification`):** `VSnackbar` ya está en Vuetify y sigue el tema.
- **Opción «Todos» con `value: null` en combos:** confunde «sin filtro» con un valor. Se usa `clearable`.
