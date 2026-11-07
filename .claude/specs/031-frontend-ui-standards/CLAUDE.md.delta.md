# 031 · frontend-ui-standards — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/frontend-ui.md") Navegación y encabezados
- Todo módulo nuevo se registra en `front-vue/src/navigation/vertical/index.js` bajo la sección que le corresponde, en este orden fijo: INVENTARIO · COMERCIAL · OPERACIONES · FINANZAS · TALLER · MANTENIMIENTO. Una sección nueva requiere actualizar esta regla.
- Todo ítem del menú (link, grupo e hijo) declara `icon: { icon: 'tabler-…' }` explícito y único. Prohibido depender del fallback `tabler-circle`.
- Toda página declara en `definePage` `meta.title`, `meta.icon` (el mismo del menú) y `meta.description`, y se encabeza con `<PageHeader />`. Prohibido un `VCardTitle`/`VCardSubtitle` o `VCard title` propio como encabezado de página.
- El breadcrumb sale de `findNavTrail()` + `meta.title`. Prohibido derivar textos del *path* de la URL.

## (+ en ".agents/rules/frontend-ui.md") Listados
- Todo listado usa `ServerDataTable` + `useServerTable`. Prohibidos `VTable` + `VPagination` y `VDataTableServer` directo en páginas.
- El total se pasa solo con `:total-items`.
- Las acciones por fila usan `RowActionButton` (ver = `info`, editar = `success`, eliminar = `error`).
- Los botones Nuevo y Exportar se habilitan con `:creatable="can('<mod>.create')"` y `:exportable="can('<mod>.export')"`.
- La exportación de un listado es `GET /api/v1/<recurso>/export` con permiso `<mod>.export`. Implementa una subclase de `App\Support\Export\CsvExport` y reutiliza `<Servicio>::query()`, el mismo del `index`.

## (+ en ".agents/rules/frontend-ui.md") Confirmaciones y notificaciones
- Toda eliminación usa `useConfirm().confirmDelete({ entity, name })`. Un rechazo de la API se muestra con `useConfirm().alert({ message })`. Prohibidos diálogos de confirmación propios, `window.confirm` y la opción `confirmColor`.
- Todo crear, actualizar o eliminar exitoso se notifica con `useNotify().success('<Entidad> <acción> correctamente')`. Los errores 422 de formulario no generan toast.

## (+ en ".agents/rules/frontend-ui.md") Inputs y combos
- Los campos usan `VTextField`, `VTextarea`, `VSelect`, `VAutocomplete`, `VCombobox` y `VFileInput` con `label` (flotante) y `placeholder` con un **ejemplo** del valor. Los obligatorios llevan ` *` en el label. Prohibidos `AppTextField`, `AppSelect`, `AppAutocomplete`, `AppTextarea` y `AppCombobox` fuera de `src/@core`.
- Se usa `VSelect` para listas fijas de hasta 15 opciones y `VAutocomplete` para listas largas o remotas. Los combos opcionales y los filtros son `clearable`. Prohibida la opción `{ value: null, title: 'Todos …' }`. El filtro de estado usa `STATUS_OPTIONS` de `utils/options.js`.
- Los filtros de la barra de la tabla usan `density="compact"`; los formularios, la densidad por defecto (`comfortable`).

## (~ en ".agents/rules/ubigeo.md") Frontend
- `UbigeoSelect` usa `VAutocomplete` con label flotante. Se reemplaza la mención a `AppTextField` por «prohibido un campo de texto libre para el código de ubigeo».
