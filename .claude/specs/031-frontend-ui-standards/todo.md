# 031 · frontend-ui-standards — TODO
> **Spec:** 031 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** 008, 030
> **Status:** Done
> **Última actualización:** 2026-10-16
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | front | S | `plugins/vuetify/{index,defaults}.js`, `themeConfig.js`, `.env.example`, `utils/options.js` | — |
| T-2 | ✅ | front | M | `navigation/vertical/index.js`, `utils/navigation.js`, `DefaultLayoutWithVerticalNav.vue`, `pages/second-page.vue` | — |
| T-3 | ✅ | front | M | `PageBreadcrumbs.vue`, `definePage` de todas las páginas | T-2 |
| T-4 | ✅ | front | S | `components/common/PageHeader.vue` | T-3 |
| T-5 | ✅ | front | M | `useConfirm.js`, `GlobalConfirmDialog.vue`, `dialogs/ConfirmDialog.vue` (borrar) | — |
| T-6 | ✅ | front | S | `useNotify.js`, `GlobalSnackbar.vue`, `App.vue` | — |
| T-7 | ✅ | front | M | `ServerDataTable.vue`, `data-table/DataTableFooter.vue`, `data-table/RowActionButton.vue` | T-1 |
| T-8 | ✅ | front | M | `data-table/DataTableToolbar.vue`, `data-table/DataTableColumnMenu.vue` | T-7 |
| T-9 | ✅ | front | M | `composables/useServerTable.js` | T-6, T-8 |
| T-10 | ✅ | back | M | `app/Support/Export/CsvExport.php`, `RolePermissionSeeder.php` | — |
| T-11 | ✅ | back | M | `Maintenance/{Services,Controllers,Exports}` (Branch, Area, Employee), `routes/api.php` | T-10 |
| T-12 | ✅ | back | M | `Clients/…`, `Users/…` exports, `routes/api.php` | T-10 |
| T-13 | ✅ | front | M | `pages/maintenance/{branches,areas,employees}/index.vue`, `EmployeeAccessCard.vue` | T-4, T-5, T-9, T-11 |
| T-14 | ✅ | front | M | `pages/{clients,users}/index.vue` | T-4, T-5, T-9, T-12 |
| T-15 | ✅ | front | M | `pages/{roles,permissions,audit}/index.vue` | T-4, T-5, T-9 |
| T-16 | ✅ | front | M | `UbigeoSelect.vue`, `CompanyForm.vue`, `ParametersTable.vue`, `pages/configuration`, `profile/components/*Tab.vue`, `pages/profile`, `login.vue` | T-4 |
| T-17 | ✅ | front | M | `*FormDrawer.vue` (Client, User, Role, Permission, Branch, Area, Employee), `RolePermissionsDialog.vue` | T-1 |
| T-18 | ✅ | front | S | `src/tests/unit/uiGuardrails.spec.js`, `routeMeta.spec.js` | T-13 … T-17 |
| T-19 | ✅ | docs | S | `.agents/rules/frontend-ui.md`, `.claude/specs/README.md` | T-18 |

---
## Detalle de Tareas

### T-1 — Locale español de Vuetify, defaults de inputs y nombre de la app
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `front-vue/src/plugins/vuetify/index.js`, `front-vue/src/plugins/vuetify/defaults.js`
- `front-vue/themeConfig.js`, `front-vue/.env.example`
- `front-vue/src/utils/options.js` (nuevo)
**Descripción:**
1. Registrar el locale `es` de `vuetify/locale` (design §8.1).
2. Ajustar los defaults de `VTextField`, `VTextarea`, `VSelect`, `VAutocomplete`, `VCombobox` y `VSnackbar` según design §8.1.
3. `themeConfig.app.title = import.meta.env.VITE_APP_NAME || 'ERP'` e `i18n.defaultLocale = 'es'`. Agregar `VITE_APP_NAME=ERP` a `.env.example`.
4. Crear `utils/options.js` con `STATUS_OPTIONS`.
**Tests:** manual. La paginación de cualquier `VDataTableServer` muestra textos en español.
**Criterios de done:**
- [x] No queda «vuexy» visible en el título de la pestaña ni en el logo del sidebar.
- [x] Frontend: `cd front-vue && npm run lint` en verde.

### T-2 — Sidebar por secciones con íconos y filtro ACL
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `front-vue/src/navigation/vertical/index.js`
- `front-vue/src/utils/navigation.js` (nuevo)
- `front-vue/src/layouts/components/DefaultLayoutWithVerticalNav.vue`
- `front-vue/src/pages/second-page.vue` (borrar)
- `front-vue/src/tests/unit/navigation.spec.js` (nuevo)
**Descripción:**
1. Reescribir la navegación con la tabla de design §8.2: headings `COMERCIAL` y `MANTENIMIENTO`; grupos «Mantenimiento» y «Seguridad»; íconos en todos los ítems.
2. Implementar `filterNavByAcl(items, can)` y `findNavTrail(items, routeName)` en `utils/navigation.js`.
3. En el layout vertical, pasar el menú filtrado (computed reactivo a la *ability* CASL).
4. Borrar `second-page.vue` y sus referencias.
**Tests:**
- `navigation.spec.js` — íconos explícitos (≠ `tabler-circle`) en links, grupos e hijos; headings en mayúsculas; headings y grupos vacíos ocultos por ACL; `findNavTrail`.
**Criterios de done:**
- [x] Un usuario sin `clients.view` no ve la cabecera COMERCIAL.
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-3 — Breadcrumb en español y metadatos de ruta
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `front-vue/src/layouts/components/PageBreadcrumbs.vue`
- `definePage` de: `pages/{clients,users,roles,permissions,audit,configuration,profile}/index.vue`, `pages/maintenance/{branches,areas,employees}/index.vue`, `pages/index.vue`, `pages/[...error].vue`
- `front-vue/src/tests/unit/PageBreadcrumbs.spec.js` (nuevo)
**Descripción:**
1. Agregar `meta.title`, `meta.icon` (igual al del menú) y `meta.description` a cada página (design §8.3).
2. Reescribir `PageBreadcrumbs.vue`: título desde `meta.title`, ruta desde `findNavTrail`, fallback `[Inicio, meta.title]`. Eliminar `humanize()`.
**Tests:**
- `PageBreadcrumbs.spec.js` — `/maintenance/branches` → «Inicio › Mantenimiento › Sedes»; `/profile` → «Inicio › Mi cuenta»; no aparece «Maintenance».
**Criterios de done:**
- [x] Ninguna ruta muestra textos en inglés en el navbar.
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-4 — Componente `PageHeader`
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/components/common/PageHeader.vue` (nuevo)
**Descripción:**
1. Implementar según design §8.4: lee `route.meta` por defecto, las props lo sobrescriben, tiene slot `actions` y es responsive.
**Tests:** se cubre en T-18 (`routeMeta.spec.js`) y con revisión visual en las migraciones.
**Criterios de done:**
- [x] Se ve bien en tema claro y oscuro, a 375 px y a 1440 px.
- [x] `npm run lint` en verde.

### T-5 — `useConfirm` unificado (confirmDelete, alert, hideCancel)
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `front-vue/src/composables/useConfirm.js`
- `front-vue/src/components/common/GlobalConfirmDialog.vue`
- `front-vue/src/components/dialogs/ConfirmDialog.vue` (borrar; verificar con grep que no se importa)
- `front-vue/src/tests/unit/useConfirm.spec.js`
**Descripción:**
1. Agregar `hideCancel`, `confirmDelete({ entity, name })` y `alert({...})` según design §8.7.
2. Un `confirm` concurrente resuelve el anterior con `false`.
3. Diálogo `persistent`, `Esc` = cancelar, foco inicial en confirmar, respeta `hideCancel`.
4. Borrar `ConfirmDialog.vue`.
**Tests:**
- `useConfirm.spec.js` — `hideCancel`, textos de `confirmDelete`, resolución de `alert` y concurrencia.
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-6 — Toasts globales `useNotify`
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `front-vue/src/composables/useNotify.js` (nuevo)
- `front-vue/src/components/common/GlobalSnackbar.vue` (nuevo)
- `front-vue/src/App.vue`
- `front-vue/src/tests/unit/useNotify.spec.js` (nuevo)
**Descripción:**
1. Cola reactiva con `success`, `error`, `info` y `warning` (design §8.8).
2. Montar `GlobalSnackbar` en `App.vue` junto a `GlobalConfirmDialog`.
**Tests:**
- `useNotify.spec.js` — encolado, tipos y desencolado al cerrar.
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-7 — `ServerDataTable` v2: cuerpo, footer, empty state y acciones de fila
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `front-vue/src/components/common/ServerDataTable.vue`
- `front-vue/src/components/common/data-table/DataTableFooter.vue` (nuevo)
- `front-vue/src/components/common/data-table/RowActionButton.vue` (nuevo)
- `front-vue/src/tests/unit/ServerDataTable.spec.js` (nuevo)
**Descripción:**
1. Props y emits de design §8.5. Prop única `totalItems`; `v-model:options`; `hide-default-footer` + `DataTableFooter`.
2. Empty state con CTA (`creatable` + `createLabel`) y mensaje según haya búsqueda o filtros.
3. `RowActionButton` con el mapa view/edit/delete.
4. Soporte de `maxWidth` con elipsis y tooltip en headers.
**Tests:**
- `ServerDataTable.spec.js` — «Mostrando 11–20 de 352», «Sin registros», empty state con y sin CTA, `RowActionButton` (color, ícono, `aria-label`).
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-8 — Barra de herramientas y selector de columnas
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-7
**Archivos clave:**
- `front-vue/src/components/common/data-table/DataTableToolbar.vue` (nuevo)
- `front-vue/src/components/common/data-table/DataTableColumnMenu.vue` (nuevo)
- `front-vue/src/components/common/ServerDataTable.vue`
- `front-vue/src/tests/unit/ServerDataTable.spec.js`
**Descripción:**
1. Toolbar según design §8.5: contador singular/plural con `es-PE`, búsqueda, slot `filters` y botones refrescar/exportar/importar/nuevo (importar abre un `input[type=file]` oculto `accept=".csv,.xlsx"` y emite `import(File)`).
2. Menú de columnas con persistencia `dt-columns:<userId>:<tableId>` y «Restablecer». Respeta `alwaysVisible` y `hidden`.
3. Layout responsive (se apila en `< sm`).
**Tests:**
- Ampliar `ServerDataTable.spec.js` — botones según props, emisiones, persistencia y restauración de columnas.
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-9 — Composable `useServerTable`
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6, T-8
**Archivos clave:**
- `front-vue/src/composables/useServerTable.js` (nuevo)
- `front-vue/src/tests/unit/useServerTable.spec.js` (nuevo)
**Descripción:**
1. Implementar design §8.6: options, search, filters, debounce de 350 ms, reset de página, limpieza de params, conversión booleana de `STATUS_OPTIONS`, `reload`, `export(url, baseName)` con `downloadFile` + `exporting` + toast de error.
**Tests:**
- `useServerTable.spec.js` — con fake timers: debounce, página 1 al filtrar, params sin vacíos, export sin `page`/`per_page`.
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-10 — Exportador CSV genérico y permisos `*.export`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Export/CsvExport.php` (nuevo)
- `back-laravel/database/seeders/RolePermissionSeeder.php`
- `back-laravel/tests/Feature/Support/CsvExportTest.php` (nuevo)
**Descripción:**
1. Clase abstracta según design §2.2: BOM, `lazy(500)`, `prepareChunk`, formatos Sí/No y fechas, neutralización de CSV injection y nombre de archivo con hora `America/Lima`.
2. Agregar los 5 permisos `*.export` con nombre y descripción en español, asignados al rol administrador.
**Tests:**
- `CsvExportTest.php` — exportador de prueba sobre un modelo existente: BOM, encabezados, formato, injection y filename.
**Criterios de done:**
- [x] `make test-filter FILTER=CsvExportTest` y `cd back-laravel && vendor/bin/pint --test` en verde.

### T-11 — Export de Sedes, Áreas y Empleados
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-10
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Services/{Branch,Area,Employee}Service.php` (`query()` + `list()`)
- `back-laravel/app/Modules/Maintenance/Exports/{Branch,Area,Employee}CsvExport.php` (nuevos)
- `back-laravel/app/Modules/Maintenance/Controllers/{Branch,Area,Employee}Controller.php`
- `back-laravel/routes/api.php`
- `back-laravel/tests/Feature/Modules/Maintenance/{Branch,Area,Employee}ExportApiTest.php` (nuevos)
**Descripción:**
1. Separar `query()` de `list()` sin cambiar el comportamiento del `index` (los tests existentes deben seguir en verde).
2. Exportadores con columnas de design §2.3; ubicación con `UbigeoService::describe()`.
3. Rutas `GET /export` antes de `/{id}`, con `permission:<mod>.export`, conservando el middleware `branch` donde ya está.
**Tests:**
- `*ExportApiTest.php` — 403 sin permiso, headers, filtros, más filas que `per_page`, alcance de sede (Empleados). Usan `SeedsUbigeoFixture`.
**Criterios de done:**
- [x] `make test` y `vendor/bin/pint --test` en verde.

### T-12 — Export de Clientes y Usuarios
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-10
**Archivos clave:**
- `back-laravel/app/Modules/Clients/{Services,Controllers,Exports}/…`
- `back-laravel/app/Modules/Users/{Services,Controllers,Exports}/…`
- `back-laravel/routes/api.php`
- `back-laravel/tests/Feature/Modules/Clients/ClientExportApiTest.php`, `back-laravel/tests/Feature/Modules/Users/UserExportApiTest.php` (nuevos)
**Descripción:** igual que T-11 para Clientes y Usuarios. En Usuarios, roles y sedes van como lista separada por `; ` y la carga es *eager* (sin N+1).
**Tests:** como T-11.
**Criterios de done:**
- [x] `make test` y `vendor/bin/pint --test` en verde.

### T-13 — Migrar Sedes, Áreas y Empleados al estándar
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-5, T-9, T-11
**Archivos clave:**
- `front-vue/src/pages/maintenance/{branches,areas,employees}/index.vue`
- `front-vue/src/modules/maintenance/components/EmployeeAccessCard.vue`
- stores de `modules/maintenance/stores/` (exponer `meta.total` de forma uniforme)
**Descripción:**
1. `PageHeader` dentro de la `VCard`; `ServerDataTable` con `useServerTable`, `exportable`, filtros `clearable` con `STATUS_OPTIONS` y `RowActionButton`.
2. Eliminación con `confirmDelete` + `alert` + `notify.success`. Quitar `confirmColor` y `hideCancel` mal usados.
3. Toasts en crear y actualizar.
**Tests:** `npm run test:unit` existente; revisión manual de cada pantalla.
**Criterios de done:**
- [x] «No se puede eliminar» sale en `warning` y con un solo botón.
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-14 — Migrar Clientes y Usuarios al estándar
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-5, T-9, T-12
**Archivos clave:**
- `front-vue/src/pages/clients/index.vue`
- `front-vue/src/pages/users/index.vue` (eliminar el diálogo propio de eliminación y `VTable` + `VPagination`)
**Descripción:** como T-13. Usuarios pasa de `VTable` a `ServerDataTable`; sus filtros de rol y estado se vuelven `VSelect` `clearable` sin opción «Todos».
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-15 — Migrar Roles, Permisos y Auditoría
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-5, T-9
**Archivos clave:**
- `front-vue/src/pages/{roles,permissions,audit}/index.vue`
**Descripción:**
1. `VTable` + `VPagination` → `ServerDataTable` (`exportable` no aplica en este spec).
2. `PageHeader`. Auditoría: título «Auditoría», descripción en español sin «(Audit Trail)».
3. Acciones de fila con `RowActionButton` (Auditoría: `action="view"` abre `AuditDetailDrawer`).
**Criterios de done:**
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-16 — Label flotante en UbigeoSelect, Configuración, Perfil y Login
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/components/common/UbigeoSelect.vue` + `src/tests/unit/UbigeoSelect.spec.js`
- `front-vue/src/modules/configuration/components/{CompanyForm,ParametersTable}.vue`, `front-vue/src/pages/configuration/index.vue`
- `front-vue/src/modules/profile/components/{AccountDetailsTab,SecurityTab,SessionsTab}.vue`, `front-vue/src/pages/profile/index.vue`
- `front-vue/src/pages/login.vue`
**Descripción:**
1. `App*` → `V*` con `label` + `placeholder` de ejemplo (design §8.9). ` *` en obligatorios.
2. `PageHeader` en Configuración y Mi cuenta.
3. `SessionsTab`: la revocación usa `confirm({ color: 'warning' })` y `notify.success`.
**Criterios de done:**
- [x] El contrato de `UbigeoSelect` (props y evento `change`) no cambia.
- [x] `npm run lint` y `npm run test:unit` en verde.

### T-17 — Label flotante en drawers y diálogos de formulario
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `front-vue/src/modules/{clients,users,roles,permissions,maintenance}/components/*FormDrawer.vue`
- `front-vue/src/modules/roles/components/RolePermissionsDialog.vue`
**Descripción:**
1. `App*` → `V*`. Revisar que cada `placeholder` sea un ejemplo y no repita el label.
2. Combos: `VSelect` o `VAutocomplete` según la regla de US-11, `clearable` en opcionales y `chips` + `closable-chips` en múltiples.
**Criterios de done:**
- [x] `npm run lint` en verde.

### T-18 — Tests guardrail de UI y metadatos de ruta
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-13, T-14, T-15, T-16, T-17
**Archivos clave:**
- `front-vue/src/tests/unit/uiGuardrails.spec.js` (nuevo)
- `front-vue/src/tests/unit/routeMeta.spec.js` (nuevo)
**Descripción:**
1. Guardrail según design §8.10.
2. `routeMeta.spec.js`: toda página con `meta.action` declara `title`, `icon` y `description`, y `meta.icon` coincide con el ícono del menú.
**Criterios de done:**
- [x] `npm run test:unit` en verde (sin excepciones *ad hoc*).

### T-19 — Regla dura y cierre del spec
**Estado:** ✅ · **Subj:** docs · **Complejidad:** S · **Depende de:** T-18
**Archivos clave:**
- `.agents/rules/frontend-ui.md` (nuevo, desde `CLAUDE.md.delta.md`)
- `.agents/rules/ubigeo.md` (ajuste de `UbigeoSelect`)
- `.claude/specs/README.md`
**Descripción:**
1. Mergear el delta en `.agents/rules/`.
2. Marcar el spec como `Done` y correr `graphify update .`.
**Criterios de done:**
- [x] Reglas publicadas y estado actualizado.
---
