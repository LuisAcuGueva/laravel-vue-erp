# 034 · purchase-receipt-installment-plan — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Cronograma de cuotas al recibir una compra
- Un cronograma de cuotas (`installments: [{due_date, amount}]`) solo se acepta en el endpoint que **crea** la factura del proveedor (`POST /purchases/receipts/{id}/confirm` y `POST /purchases/orders/{id}/receive-all`, en la recepción que genera la `SupplierInvoice` por primera vez para ese número de documento). Nunca se aplica cuando la recepción se fusiona en una factura ya existente (mismo proveedor + mismo número de documento): esa recepción solo suma su monto a la cuota vigente y el cronograma enviado se ignora, devolviendo un aviso en `meta.warnings`.
- Si el usuario envía `installments` explícitamente y no cuadra con el total de la factura generada (o alguna fecha es anterior a la emisión), la confirmación de la recepción responde 422 y la recepción **no** queda confirmada — a diferencia de cualquier otro fallo de la generación automática de factura (p. ej. falta de tipo de cambio), que nunca bloquea la recepción. La distinción es: error del propio usuario → bloquea; condición ajena al usuario → nunca bloquea, la recepción queda confirmada sin factura/cuotas automáticas para registrarse manualmente después.

## (+ en ".agents/rules/frontend-ui.md") Calculadora de cuotas de proveedor
- Toda pantalla que permita definir un cronograma de cuotas a un proveedor (al registrar una factura manual o al confirmar una recepción con `provider_document_type = invoice`) usa el componente `front-vue/src/modules/purchases/components/payables/InstallmentPlanFields.vue`. Prohibido duplicar la lógica de reparto de cuotas (`splitInstallments`) fuera de ese componente.
- El total que alimenta esa calculadora en pantallas sin un documento ya priceado (recepciones) se calcula con `calculateDocumentTotals()` (`front-vue/src/utils/documentTotals.js`) usando la tasa de impuesto de la empresa (`CompanySettingService::current()`), nunca la tasa de la orden de compra — es solo una previsualización, el backend es la fuente de verdad del total real.
