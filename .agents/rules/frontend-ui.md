---
trigger: always_on
description: Sistema de UI único del frontend (navegación, encabezados, listados, confirmaciones, toasts, inputs y combos) y exportación CSV de listados.
---

## Navegación y encabezados

- Todo módulo nuevo se registra en `front-vue/src/navigation/vertical/index.js` bajo la sección que le corresponde, en este orden fijo: INVENTARIO · COMERCIAL · OPERACIONES · FINANZAS · TALLER · MANTENIMIENTO. Una sección nueva requiere actualizar esta regla. Las secciones sin ítems no se declaran; su visibilidad la calcula `filterNavByAcl()` (`utils/navigation.js`).
- Todo ítem del menú (link, grupo e hijo) declara `icon: { icon: 'tabler-…' }` explícito y único. Prohibido depender del fallback `tabler-circle`.
- Toda página declara en `definePage` `meta.title`, `meta.icon` (el mismo del menú) y `meta.description`, y se encabeza con `<PageHeader />` (`components/common/PageHeader.vue`). Prohibido un `VCardTitle`/`VCardSubtitle` o `VCard title` propio como encabezado de página.
- `meta.action` de una página y `action` de su ítem del menú son un permiso o una lista de permisos (basta uno). Una página con pestañas por permiso usa la lista, muestra cada pestaña con su `can('<mod>.view')` y abre en la primera disponible.
- El breadcrumb sale de `findNavTrail()` + `meta.title`. Prohibido derivar textos del *path* de la URL.

## Cards

- Toda `VCard` de página (contenedor de encabezado, filtros, formulario, listado o sección) usa el estilo por defecto del tema: fondo blanco y sombra externa (`elevation`), sin `variant="outlined"` ni `variant="text"` y sin borde propio. Prohibido declarar `variant="outlined"` en una `VCard` de nivel de página — es el patrón que generó bordes con fondo transparente en el módulo POS (`front-vue/src/pages/sales/pos/index.vue`) y debe evitarse en UI nueva.
- Excepción: una `VCard` anidada dentro de otra `VCard` (p. ej. un bloque secundario dentro de un card ya elevado) puede usar `variant="outlined"` o `variant="tonal"` para diferenciarse visualmente de su contenedor padre, ya que ambos compartirían la misma sombra si fueran elevados.

## Listados

- Todo listado usa `ServerDataTable` + `useServerTable` (`v-model:options`, `v-model:search`, `:has-filters`). Prohibidos `VTable` + `VPagination` y `VDataTableServer` directo en páginas.
- El total se pasa solo con `:total-items`.
- Las acciones por fila usan `RowActionButton` (ver = `info`, editar = `success`, eliminar = `error`, otras = `custom`/`primary`).
- Los botones Nuevo y Exportar se habilitan con `:creatable="can('<mod>.create')"` y `:exportable="can('<mod>.export')"`. Los filtros van en el slot `#filters`.
- Si la API espera un formato distinto al de los filtros (ej. `status=active`), se adapta con `useServerTable({ mapParams })`, que aplica al listado y a la exportación.
- La exportación de un listado es `GET /api/v1/<recurso>/export` con permiso `<mod>.export` (registrado en `RolePermissionSeeder`), declarada antes de `/{id}`. Implementa una subclase de `App\Support\Export\CsvExport` y reutiliza `<Servicio>::query()`, el mismo del `index`. Un `branch_id` recibido como filtro se valida con `BranchAccessGuard::assertAccessible()`.

## Confirmaciones y notificaciones

- Toda eliminación usa `useConfirm().confirmDelete({ entity, name })`. Un rechazo de la API se muestra con `useConfirm().alert({ message })`. Prohibidos diálogos de confirmación propios, `window.confirm` y la opción `confirmColor`.
- Todo crear, actualizar o eliminar exitoso se notifica con `useNotify().success('<Entidad> <acción> correctamente')`. Los errores 422 de formulario no generan toast.

## Inputs y combos

- Los campos usan `VTextField`, `VTextarea`, `VSelect`, `VAutocomplete`, `VCombobox` y `VFileInput` con `label` (flotante) y `placeholder` con un **ejemplo** del valor. Los obligatorios llevan ` *` en el label. Prohibidos `AppTextField`, `AppSelect`, `AppAutocomplete`, `AppTextarea` y `AppCombobox` fuera de `src/@core`.
- Se usa `VSelect` para listas fijas de hasta 15 opciones y `VAutocomplete` para listas largas o remotas. Los combos opcionales y los filtros son `clearable`; los múltiples usan `chips` + `closable-chips`. Prohibida la opción `{ value: null, title: 'Todos …' }`. El filtro de estado usa `STATUS_OPTIONS` de `utils/options.js`.
- Los filtros de la barra de la tabla usan `density="compact"`; los formularios, la densidad por defecto (`comfortable`).
- `src/tests/unit/uiGuardrails.spec.js` y `routeMeta.spec.js` verifican estas reglas; no se les agregan excepciones *ad hoc*.

## Calculadora de cuotas de proveedor

- Toda pantalla que permita definir un cronograma de cuotas a un proveedor (al registrar una factura manual o al confirmar una recepción con `provider_document_type = invoice`) usa el componente `front-vue/src/modules/purchases/components/payables/InstallmentPlanFields.vue`. Prohibido duplicar la lógica de reparto de cuotas (`splitInstallments`) fuera de ese componente.
- El total que alimenta esa calculadora en pantallas sin un documento ya priceado (recepciones) se calcula con `calculateDocumentTotals()` (`front-vue/src/utils/documentTotals.js`) usando la tasa de impuesto de la empresa (`CompanySettingService::current()`), nunca la tasa de la orden de compra — es solo una previsualización, el backend es la fuente de verdad del total real.
- El pago mixto (monto al contado + saldo financiado) usa `front-vue/src/modules/purchases/components/payables/DownPaymentFields.vue` (reutiliza `PaymentMethodFields.vue` para método/caja/referencia), visible solo con `can('supplier_payments.create')`. El `total` que recibe `InstallmentPlanFields` en esa misma pantalla es el saldo **restante** (total − monto al contado), no el total completo.
