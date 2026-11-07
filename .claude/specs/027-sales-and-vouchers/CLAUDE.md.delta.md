# 027 · sales-and-vouchers — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (~ en ".agents/rules/document-numbering.md") Series de comprobantes de venta
- Regla anterior (spec 014): "Las series de comprobantes de venta … usan `DocumentSeriesService` (spec 027)". Se concreta así:
- Los comprobantes (factura, boleta, nota de venta, nota de crédito) obtienen su número solo con `DocumentSeriesService::next()` dentro de la transacción de emisión; la tabla `sales` (y `credit_notes`) tiene `unique(document_series_id, number)`.
- Un número emitido nunca se reutiliza ni se edita, aunque el documento se anule.

## (+ en ".agents/rules/sales.md") Emisión de comprobantes y facturación electrónica
- Toda venta se emite con `SaleIssuanceService::issue()`, que orquesta en una sola transacción el correlativo, el congelamiento del cliente, los totales, la política de descuentos, el stock (ledger) y los cobros (`MoneySettlementService`). Ningún otro código crea `Sale` directamente.
- Los datos del cliente impresos en un comprobante se leen de las columnas congeladas de la venta (`client_document_*`, `client_name`, `client_address`), nunca de la tabla `clients`.
- La facturación electrónica se invoca solo a través de `ElectronicInvoicingDriver` (resuelto por `EInvoicingManager` según `config('einvoicing.driver')`) y siempre en un job en cola despachado después del commit; un fallo del driver nunca revierte la emisión local.
- Un comprobante aceptado por SUNAT no se anula: se corrige con una nota de crédito (spec 029).
- Los eventos de dominio (`SaleIssued`, `SaleCancelled`) se despachan después del commit (`DB::afterCommit`), y otros módulos (cuentas por cobrar) reaccionan a ellos con listeners en lugar de modificar el servicio de emisión.
