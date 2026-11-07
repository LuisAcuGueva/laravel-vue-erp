# 026 · quotations — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/sales.md", trigger: always_on) Precios y descuentos de venta
**description:** Reglas de precios de lista y descuentos en cotizaciones, ventas y notas de crédito.
- Los precios de lista de venta se obtienen solo con `Sales\Services\SalePricingService::listPrice()` (tipo de precio, mínimo mayorista y moneda); las líneas guardan `list_price` junto al `unit_price` cobrado.
- Todo documento de venta con descuentos pasa por `DiscountPolicyService::validate()` antes de guardarse. El descuento efectivo considera tanto el `discount_percent` como la rebaja del `unit_price` frente al `list_price`; superar `sales.max_line_discount_percent` o `sales.max_global_discount_percent` exige `sales.discount_override` y se registra en `discount_override_by`.
- No existen promociones, cupones ni membresías en el sistema; no se agregan descuentos automáticos sin un spec que lo defina.
- Las tareas periódicas de ventas (vencimiento de cotizaciones, cuotas vencidas) se registran en `routes/console.php` con `withoutOverlapping()`.
