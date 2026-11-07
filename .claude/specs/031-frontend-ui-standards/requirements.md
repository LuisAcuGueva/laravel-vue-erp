# 031 · frontend-ui-standards — Requirements
> **Spec:** 031 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** 008, 030
> **Status:** Done
> **Última actualización:** 2026-10-16
> **Autores:** Luis Acuña Guevara
> **Origen:** Requerimiento transversal — estandarización de la UI (sidebar por secciones, tabla profesional, alertas, inputs, encabezados y breadcrumb en español)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo.
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - [008-frontend-crud-kit](../008-frontend-crud-kit/requirements.md) — origen de `ServerDataTable`, `useConfirm` y `useApiFormErrors`, que este spec evoluciona.
>
---
## 1. Contexto de Negocio
El sistema crecerá con unos 17 specs funcionales más (013–029: inventario, compras, ventas, finanzas). Hoy cada pantalla resuelve la UI a su manera, y eso se nota:

| Aspecto | Estado actual (código real) |
|---|---|
| Sidebar | Lista plana en `front-vue/src/navigation/vertical/index.js`, sin secciones. `Sedes` y `Áreas` no tienen ícono (caen al fallback `tabler-circle` de `themeConfig.verticalNav.defaultNavItemIconProps`). Sigue el ítem de demo `Second page`. |
| Tablas | Sedes, Áreas, Empleados y Clientes usan `ServerDataTable`. Usuarios, Roles, Permisos y Auditoría usan `VTable` + `VPagination` hechos a mano. No hay contador de registros, ni footer uniforme, ni selector de columnas, ni exportación. La prop se llama `totalItems`, pero 3 páginas le pasan `items-length` y funcionan solo por *fallthrough* de atributos. |
| Alertas de eliminación | `useConfirm` acepta `color`, pero Sedes, Áreas, Empleados y `EmployeeAccessCard` le pasan `confirmColor` y `hideCancel`, que se ignoran: el aviso «No se puede eliminar» sale rojo y con botón Cancelar. Usuarios tiene un diálogo de eliminación propio (`pages/users/index.vue:427`). `components/dialogs/ConfirmDialog.vue` (Vuexy, textos «Confirm/Cancel» en inglés) sigue en el repo. Después de eliminar no hay ninguna confirmación visual de éxito. |
| Inputs | Hay dos estilos mezclados. `VTextField` con *label* flotante (form de Sede, `BranchFormDrawer.vue`) y `AppTextField`/`AppSelect`/`AppAutocomplete` con *label* fijo encima (Clientes, Usuarios, Configuración, Perfil, `UbigeoSelect`). |
| Combos | El estado «todos» se modela con una opción `{ title: 'Todos los estados', value: null }` en Sedes y Usuarios; otros módulos no la tienen. |
| Encabezado de página | Sedes, Áreas y Empleados usan `VCardTitle` + `VCardSubtitle`; Clientes y Usuarios usan `VCard title="Gestión de …"`; Auditoría tiene un título mixto en inglés. Ninguno tiene ícono. |
| Breadcrumb | `layouts/components/PageBreadcrumbs.vue` humaniza el *path*: `/maintenance/branches` se muestra «Maintenance › Branches». `themeConfig.app.title = 'vuexy'` e `i18n.defaultLocale = 'en'`. Los textos internos de Vuetify (paginación, «Items per page», «No data available») salen en inglés. |

Este spec fija un **sistema de UI único** que deben usar los módulos actuales y todos los futuros, y migra las pantallas existentes a ese sistema.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario interno | Cualquier usuario autenticado del ERP | JWT; ve solo los ítems de menú y acciones que permiten sus permisos (CASL) |
| Usuario con exportación | Usuario con permiso `<modulo>.export` | JWT + permiso Spatie `<modulo>.export` |
| Desarrollador / agente | Quien implementa los specs 013–029 | Sigue `.agents/rules/frontend-ui.md` |

## 3. User Stories (formato EARS)

### US-1 — Sidebar agrupado por secciones
**Como** usuario **quiero** ver el menú agrupado por secciones (INVENTARIO, COMERCIAL, OPERACIONES, FINANZAS, MANTENIMIENTO…) **para** ubicar rápido los módulos.
**Cuando** se renderiza el menú vertical, **el sistema debe** mostrar cabeceras de sección en mayúsculas. Debajo de cada una van ítems directos y grupos con submenús, en el orden definido en el design §8.2.
**Módulo:** `Transversal` (frontend)
**Permisos:** autenticado (cada ítem conserva su `action` CASL)
**Criterios de aceptación:**
- [ ] El menú usa ítems `{ heading: 'SECCIÓN' }` de Vuexy (`VerticalNavSectionTitle`).
- [ ] Una cabecera de sección **no se muestra** si el usuario no puede ver ningún ítem de esa sección. Un grupo no se muestra si no puede ver ninguno de sus hijos.
- [ ] Desaparece el ítem `Second page`.
- [ ] `Inicio` queda primero, sin cabecera.
- [ ] Una sección puede contener a la vez enlaces directos y grupos con submenús (ej. MANTENIMIENTO → grupo «Mantenimiento» con Sedes/Áreas/Empleados, grupo «Seguridad» con Usuarios/Roles/Permisos, y los enlaces Auditoría y Configuración).

### US-2 — Todos los ítems del menú tienen ícono
**Como** usuario **quiero** que cada ítem del menú, incluso los submenús, tenga un ícono propio **para** reconocerlo de un vistazo.
**Cuando** se renderiza un enlace o grupo del menú, **el sistema debe** mostrar su ícono Tabler declarado explícitamente.
**Criterios de aceptación:**
- [ ] Todo ítem que no sea `heading` (enlaces, grupos e hijos de grupos) declara `icon: { icon: 'tabler-…' }`.
- [ ] Un test Vitest falla si algún ítem no declara ícono o lo deja en `tabler-circle`.

### US-3 — Breadcrumb y título del navbar en español
**Como** usuario **quiero** que el navbar muestre el título y la ruta de la página en español **para** no ver textos como «Maintenance › Branches».
**Cuando** se navega a una página, **el sistema debe** mostrar en el navbar el título (`route.meta.title`) y la ruta `Inicio › <Grupo…> › <Página>`. Los segmentos salen de los títulos del árbol de navegación, nunca del *path*.
**Criterios de aceptación:**
- [ ] `/maintenance/branches` muestra «Sedes» y la ruta «Inicio › Mantenimiento › Sedes».
- [ ] Una página fuera del menú (ej. `/profile`) muestra su `meta.title` («Mi cuenta») y la ruta «Inicio › Mi cuenta».
- [ ] Ningún segmento sale del *path* humanizado. Si una ruta autenticada no declara `meta.title`, un test lo detecta.
- [ ] Las cabeceras de sección (`heading`) no aparecen en el breadcrumb.
- [ ] Los textos internos de Vuetify (paginación, «No hay datos», «Cargando…») salen en español: locale `es` de Vuetify.
- [ ] `themeConfig.app.title` toma el nombre de la aplicación de `VITE_APP_NAME` (por defecto `ERP`), nunca `vuexy`.

### US-4 — Encabezado de página uniforme
**Como** usuario **quiero** que toda página de módulo tenga ícono, título y descripción **para** saber dónde estoy y qué hace la pantalla.
**Cuando** se abre una página de módulo, **el sistema debe** mostrar `PageHeader` con un avatar tonal con el ícono del módulo, el título (h5, negrita) y la descripción (texto secundario). A la derecha, opcionalmente, van acciones globales de la página.
**Criterios de aceptación:**
- [ ] `PageHeader` toma ícono, título y descripción de `route.meta` (`icon`, `title`, `description`) por defecto. Las props pueden sobrescribirlos.
- [ ] El ícono del encabezado es el mismo que el del ítem del menú.
- [ ] Todas las páginas de módulo existentes (Clientes, Usuarios, Roles, Permisos, Sedes, Áreas, Empleados, Auditoría, Configuración, Mi cuenta) lo usan.

### US-5 — Tabla de datos profesional y uniforme
**Como** usuario **quiero** que todos los listados tengan la misma tabla con barra de herramientas, paginación clara y acciones consistentes **para** trabajar igual en cualquier módulo.
**Cuando** se renderiza un listado, **el sistema debe** mostrar `ServerDataTable` con estas partes:
1. **Barra superior:** contador «N registros encontrados» a la izquierda; buscador y filtros (slot) al centro; botones circulares tonales con tooltip a la derecha: **Refrescar**, **Exportar**, **Importar**, **Nuevo**, cada uno solo si está habilitado y permitido; y el botón **Columnas**.
2. **Cuerpo:** encabezados en mayúsculas, *skeleton* al cargar y estado vacío con ícono, texto y botón «Nuevo …» si el usuario puede crear.
3. **Acciones por fila:** botones circulares *outlined* con ícono y tooltip, con colores fijos: ver = `info`, editar = `success`, eliminar = `error`, otras = `primary`.
4. **Pie:** «Mostrando 1–10 de 352» a la izquierda; a la derecha, «Filas por página» (10/25/50/100) y paginación con primera/última página.
**Módulo:** `Transversal` (frontend)
**Permisos:** cada botón recibe su visibilidad de la página (`can('<modulo>.create')`, `can('<modulo>.export')`…)
**Criterios de aceptación:**
- [ ] Una sola prop para el total: `total-items`. Se elimina el uso de `items-length`.
- [ ] El buscador aplica *debounce* de 350 ms y vuelve a la página 1 (vía `useServerTable`).
- [ ] El menú «Columnas» permite ocultar o mostrar columnas, salvo `actions` y las marcadas `alwaysVisible`. La elección se guarda por usuario y por tabla en `localStorage` (`dt-columns:<userId>:<tableId>`) y sobrevive a recargar la página.
- [ ] Los textos largos no rompen la fila en columnas angostas: la tabla usa `text-no-wrap` por defecto y las columnas de texto libre pueden declarar `maxWidth` con elipsis y tooltip.
- [ ] Usuarios, Roles, Permisos y Auditoría migran de `VTable` + `VPagination` a `ServerDataTable`.

### US-6 — Exportar listados
**Como** usuario con permiso de exportación **quiero** descargar el listado filtrado **para** trabajarlo en Excel.
**Cuando** pulso **Exportar** en un listado, **el sistema debe** descargar un CSV (UTF-8 con BOM) con **todos** los registros que cumplen los filtros y búsqueda actuales, no solo la página visible, con encabezados en español.
**Módulo:** Clients, Users, Maintenance (`GET /api/v1/{clients|users|branches|areas|employees}/export`)
**Permisos:** `clients.export`, `users.export`, `branches.export`, `areas.export`, `employees.export`
**Criterios de aceptación:**
- [ ] El endpoint respeta los mismos filtros y el mismo alcance de sede que el `index` del módulo.
- [ ] El archivo se llama `<modulo>_YYYYMMDD_HHmm.csv` (ej. `sedes_20261016_1530.csv`).
- [ ] La respuesta se genera en *streaming* por lotes; no carga todo en memoria.
- [ ] La descarga usa `utils/download.js` (regla multi-branch); el botón muestra *loading* mientras descarga.
- [ ] Sin permiso: el botón no se muestra y el endpoint responde 403.
- [ ] La ubicación se exporta como texto legible («Lima / Lima / Miraflores») vía `UbigeoService::describe()`.

### US-7 — Importar listados (contrato de componente)
**Como** desarrollador **quiero** que la tabla tenga el botón **Importar** listo **para** que los módulos que lo necesiten solo implementen su endpoint.
**Cuando** una página pasa `importable` y escucha `@import`, **el sistema debe** mostrar el botón Importar y emitir el evento con el archivo elegido (`.csv`, `.xlsx`).
**Criterios de aceptación:**
- [ ] Sin `importable`, el botón no se renderiza.
- [ ] Ningún módulo actual implementa importación en este spec (ver §6).

### US-8 — Alertas de confirmación y eliminación uniformes
**Como** usuario **quiero** que eliminar cualquier registro me pregunte siempre igual y me confirme el resultado **para** no equivocarme.
**Cuando** pulso eliminar, **el sistema debe** abrir el diálogo global con:
- ícono `tabler-trash` en avatar tonal `error`;
- título «¿Eliminar <entidad>?»;
- mensaje «Se eliminará <nombre>. Esta acción no se puede deshacer.»;
- botones «Cancelar» (tonal secundario) y «Sí, eliminar» (`error`).

Si la API rechaza la eliminación (409/422), el sistema muestra un **aviso** con ícono `tabler-alert-circle` en `warning`, título «No se puede eliminar», el mensaje de la API y un solo botón «Entendido». Si la eliminación funciona, muestra un toast «<Entidad> eliminado(a) correctamente».
**Permisos:** `<modulo>.delete`
**Criterios de aceptación:**
- [ ] `useConfirm` expone `confirm(options)`, `confirmDelete({ entity, name })` y `alert({ title, message, color })`. `options` acepta `color`, `icon`, `confirmText`, `cancelText` y `hideCancel`.
- [ ] Ningún componente arma su propio diálogo de confirmación. Se elimina el diálogo propio de `pages/users/index.vue` y el archivo `components/dialogs/ConfirmDialog.vue`.
- [ ] El diálogo no se cierra al hacer clic afuera (`persistent`). `Esc` equivale a «Cancelar».
- [ ] Mientras corre la eliminación, el botón de la fila queda en *loading* y no admite doble clic.

### US-9 — Notificaciones (toasts) de resultado
**Como** usuario **quiero** una confirmación breve después de guardar, actualizar o eliminar **para** saber que la acción se completó.
**Cuando** una acción de escritura termina, **el sistema debe** mostrar un `VSnackbar` global arriba a la derecha durante 3 s: `success` si salió bien, `error` con el mensaje de la API si falló. Los errores 422 de formulario no generan toast, porque ya se muestran en los campos.
**Criterios de aceptación:**
- [ ] `useNotify()` expone `success(msg)`, `error(msg)`, `info(msg)` y `warning(msg)`. Hay un solo `GlobalSnackbar` montado en `App.vue`.
- [ ] Todos los CRUD existentes notifican crear, actualizar y eliminar.
- [ ] Varios toasts seguidos se encolan; no se pisan.

### US-10 — Inputs con label flotante y placeholder de ejemplo
**Como** usuario **quiero** que todos los campos se comporten igual **para** una experiencia consistente.
**Cuando** un campo está vacío y sin foco, **el sistema debe** mostrar el `label` dentro del campo. **Cuando** recibe el foco, el label sube al borde y aparece el `placeholder` con un **ejemplo** del valor esperado (ej. label «Código *», placeholder «SEDE-01»). Es el comportamiento del form de Sede.
**Criterios de aceptación:**
- [ ] Los formularios y filtros usan `VTextField`, `VTextarea`, `VSelect`, `VAutocomplete`, `VCombobox` y `VFileInput` con `variant="outlined"` (por defaults de Vuetify) y siempre con `label`.
- [ ] Prohibido usar `AppTextField`, `AppSelect`, `AppAutocomplete`, `AppTextarea` y `AppCombobox` en `src/pages`, `src/modules`, `src/components` y `src/layouts`. Un test *guardrail* lo verifica. `AppDateTimePicker` queda permitido hasta que exista su equivalente (ver §7).
- [ ] Los campos obligatorios llevan ` *` al final del label.
- [ ] `placeholder` es siempre un ejemplo, nunca una repetición del label.
- [ ] Densidad: `comfortable` en formularios y `compact` en filtros de la barra de la tabla.
- [ ] `UbigeoSelect` pasa a label flotante (`VAutocomplete`).

### US-11 — Combos uniformes
**Como** usuario **quiero** que los combos se vean y se comporten igual en todos los módulos.
**Cuando** se usa un combo, **el sistema debe** aplicar estas reglas:
- `VSelect` para listas fijas y cortas (≤ 15 opciones);
- `VAutocomplete` para listas largas o remotas;
- `clearable` cuando el campo es opcional o es un filtro;
- texto de vacío «Sin resultados»;
- `chips` + `closable-chips` cuando es múltiple.
**Criterios de aceptación:**
- [ ] El filtro «todos» se expresa limpiando el combo (`clearable`), nunca con una opción `{ value: null, title: 'Todos …' }`.
- [ ] Los combos de estado activo/inactivo usan una única constante compartida `STATUS_OPTIONS` (`utils/options.js`).
- [ ] `no-data-text` en español por default de Vuetify.

## 4. Reglas de negocio e invariantes
- El orden de secciones del sidebar es fijo: (Inicio) · INVENTARIO · COMERCIAL · OPERACIONES · FINANZAS · TALLER · MANTENIMIENTO. Un spec que agrega un módulo lo ubica en la sección que le asigna el design §8.2 y no crea secciones nuevas sin actualizar esta tabla.
- El ícono de un módulo es único y es el mismo en el menú, el `meta.icon` de su página y su `PageHeader`.
- La exportación nunca devuelve más datos que el `index` del módulo para el mismo usuario y los mismos filtros (mismo alcance de sede y permisos).
- Toda eliminación pasa por `useConfirm().confirmDelete()`.

## 5. Requisitos no funcionales
- **Accesibilidad:** los botones de solo ícono tienen `aria-label` y tooltip; el diálogo atrapa el foco; el contraste de los colores de acción cumple WCAG AA en tema claro y oscuro.
- **Responsive:** en `< sm`, la barra de la tabla apila contador, filtros y botones; el pie apila «Mostrando…» y la paginación.
- **Rendimiento:** la exportación se hace en *streaming* (`lazy(500)`) con memoria acotada; un export de 50 000 filas no supera 128 MB.
- **Calidad:** `npm run lint`, `npm run test:unit` (Vitest), `make test` y `vendor/bin/pint --test` en verde.

## 6. Out of Scope
- **Importación por módulo:** solo se deja el contrato del componente (US-7). Cada módulo que necesite importar lo define en su propio spec.
- Exportación a `.xlsx` nativo o PDF: el CSV con BOM abre bien en Excel. `.xlsx` queda para un spec posterior si se pide.
- Internacionalización multi-idioma: la UI es solo en español.
- Rediseño del tema (colores, tipografía, logo): se mantiene el tema Vuexy actual.
- Módulos de los specs 013–029: no se implementan aquí, pero sus specs deben cumplir la regla `frontend-ui.md`.

## 7. Open Questions
- [ ] ¿Se usa un permiso dedicado `<modulo>.export` (propuesto) o basta `<modulo>.view`? — Responsable: Luis Acuña.
- [ ] ¿Se reemplaza `AppDateTimePicker` (label encima) por un wrapper con label flotante en este spec o cuando se use por primera vez (spec 015+)? Propuesta: cuando se use por primera vez. — Responsable: Luis Acuña.
- [ ] ¿Valor de `VITE_APP_NAME` para producción («Multinort»)? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- Ningún ítem del menú sin ícono; ninguna sección vacía visible (test de navegación en verde).
- 0 usos de `AppTextField`/`AppSelect`/`AppAutocomplete`/`AppTextarea`/`AppCombobox`/`VTable`+`VPagination` de listados y `ConfirmDialog` en `src/pages`, `src/modules` y `src/components` (test guardrail en verde).
- Las 8 páginas de listado usan `ServerDataTable` + `PageHeader` + `useServerTable`.
- Breadcrumb sin textos en inglés en todas las rutas.
- Exportación cubierta por Feature Tests en los 5 módulos.
