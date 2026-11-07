# 013 · inventory-masters — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/inventory.md", trigger: always_on) Maestros de inventario
**description:** Reglas del módulo Inventory (maestros, catálogo, stock y movimientos).
- Las conversiones entre unidades se calculan solo con `UnitService::convert()`; ningún módulo multiplica factores por su cuenta.
- Las unidades de empaque (Caja, Saco, Paquete…) pertenecen a una categoría con `is_packaging = true`: no tienen unidad base, guardan `conversion_factor = 1` sin significado y `UnitService::convert()` las rechaza. Su contenido se define solo en la presentación del producto (spec 014). `is_packaging` no cambia si la categoría tiene unidades.
- Los módulos que generan movimientos (compras, ventas, notas de crédito, traslados) usan los motivos de sistema por su código (`SystemReason` enum), nunca por id ni por nombre. Un motivo nuevo de sistema se agrega solo al enum: `Inventory\WarehouseReasonSeeder` lo sincroniza.
- Los maestros referenciados (categorías, marcas, unidades, almacenes, motivos) no se eliminan si tienen dependencias: el `Service::delete()` lanza `InventoryRuleException` (422) y la UI ofrece desactivar.

## (+ en ".agents/rules/database-primary-keys.md") Índices parciales
- Las restricciones "uno por grupo" (`is_default`, `is_base`, `is_main`) se implementan con índices únicos parciales (`WHERE flag = true AND deleted_at IS NULL`), creados solo en `pgsql`/`sqlite`, y se refuerzan en el Service dentro de una transacción.
