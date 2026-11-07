# 014 · catalog-items — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/document-numbering.md", trigger: always_on) Numeración de códigos y documentos internos
**description:** Cómo se generan códigos y números correlativos de documentos internos.
- Todo código o número correlativo interno (ítems, notas, requerimientos, órdenes, cotizaciones) se obtiene con `SequentialCodeGenerator::next($key, $prefix, $pad, $scope)` dentro de la misma transacción que crea el documento. Nunca con `max()+1` ni `count()+1`.
- La `key` es el nombre del documento en `snake_case` (`catalog_item`, `warehouse_note_in`, `purchase_order`); el `scope` es `global` o el `branch_id` cuando la numeración es por sede.
- Las series de comprobantes de venta (boleta/factura/nota de crédito) **no** usan este generador: usan `DocumentSeriesService` (spec 027).

## (+ en ".agents/rules/inventory.md") Catálogo y cantidades
- Las líneas de documentos guardan siempre `item_presentation_id`, la cantidad en la presentación y la cantidad en unidad base (`base_quantity = quantity × factor`, calculada en el backend con `ItemPresentation::toBaseQuantity()`); el stock y el kardex trabajan solo con `base_quantity`.
- Los selectores de ítems en el frontend usan `components/common/CatalogItemPicker.vue` con el `context` adecuado (`sale`, `purchase`, `stock`); no se crean autocompletes propios contra el catálogo.
- Columnas de dinero en precios y costos unitarios: `decimal(18,4)` (costo promedio `decimal(18,6)`); cantidades: `decimal(18,4)`.
