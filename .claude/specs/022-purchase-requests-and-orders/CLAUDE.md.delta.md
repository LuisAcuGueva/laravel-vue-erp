# 022 · purchase-requests-and-orders — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Totales e impuestos de documentos
- Los totales, impuestos y descuentos de todo documento comercial (OC, facturas de proveedor, cotizaciones, ventas, notas de crédito) los calcula el backend con `App\Support\Pricing\DocumentTotalsCalculator`. Los montos que envía el cliente se ignoran.
- La tasa de impuesto se toma de `company_settings.default_tax_rate` al crear el documento y se guarda en el documento (`tax_rate`), para que los cambios de tasa no alteren documentos emitidos.
- Los documentos guardan las bases por tipo de impuesto (`taxable_base`, `exempt_base`, `unaffected_base`), `discount_total`, `tax_total` y `total`; las líneas guardan `line_subtotal`, `line_tax` y `line_total`.
- En el frontend, la vista previa de totales usa `utils/documentTotals.js` (la misma fórmula, con tests espejo) y la grilla `components/common/DocumentLinesTable.vue`.

## (+ nuevo ".agents/rules/pdf-documents.md", trigger: always_on) Documentos PDF
**description:** Generación de documentos imprimibles (OC, cotizaciones, comprobantes, reportes).
- Todo PDF se genera con `App\Support\Pdf\PdfRenderer` y una vista que extiende `resources/views/pdf/layouts/document.blade.php` (cabecera con datos de empresa de `CompanySettingService`). No se instancia dompdf directamente.
- Las vistas PDF viven en `resources/views/pdf/<modulo>/<documento>.blade.php`, usan la fuente `DejaVu Sans` y no cargan recursos remotos (`enable_remote = false`); las imágenes van como data URI.
- El endpoint de PDF de un documento es `GET /<recurso>/{id}/pdf` (con `?inline=1` para previsualizar) y exige el permiso `view` del recurso.
