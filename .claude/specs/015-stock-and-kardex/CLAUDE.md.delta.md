# 015 · stock-and-kardex — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/inventory.md") Ledger de stock y kardex
- Todo cambio de existencias pasa por `StockLedgerService` (`in`, `out`, `transfer`, `reverse`, `applyBatch`) dentro de la transacción del documento de origen. Prohibido escribir `stocks.quantity`, `stocks.avg_cost` o insertar en `kardex_movements` desde otro lugar.
- `kardex_movements` es inmutable: no se actualiza ni se elimina. Las correcciones se hacen con `StockLedgerService::reverse()` o con un documento compensatorio.
- Los documentos multilínea usan `applyBatch()` para que los bloqueos se tomen en orden (`warehouse_id`, `catalog_item_id`).
- El costeo es promedio ponderado por almacén, calculado con `bcmath`; los costos de salida siempre salen del ledger, nunca del cliente.
- Todo modelo que origine movimientos registra su alias en el morph map de `InventoryServiceProvider` (`Relation::enforceMorphMap`) y lo usa como `source_type`.
- La disponibilidad de stock para validar documentos se consulta con `StockQueryService::availableFor()`; la validación definitiva es la del ledger (puede lanzar `InsufficientStockException`).
