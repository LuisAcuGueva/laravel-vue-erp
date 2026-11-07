# 029 · credit-notes — Requirements
> **Spec:** 029 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 015, 018, 024, 027, 028
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 5)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 027 — ventas, series, driver de facturación electrónica. Spec 028 — cuotas y `sale.balance`. Spec 015 — ledger de stock. Spec 024 — `MoneySettlementService`.
> - Origen Multinort: `create_credit_notes_table.php` (serie FC01/BC01, `sunat_reason_code`, estado pendiente/aplicado/anulado, aprobación), `create_credit_note_details_table.php` (`affects_inventory`), `Ingresos/CreditNoteController.php`.
>
---
## 1. Contexto de Negocio
Cuando una venta ya emitida debe corregirse (devolución de mercadería, descuento posterior, anulación de una factura aceptada por SUNAT), se emite una **nota de crédito** vinculada al comprobante. Según el caso, la nota **devuelve stock**, **reduce la deuda** de una venta al crédito y/o **reembolsa** dinero al cliente desde caja o banco.

En Multinort, `credit_notes` tiene serie (FC01/BC01), correlativo, código de motivo SUNAT (catálogo 09), sustento, totales, estado (pendiente, aplicado, anulado) y aprobación; el detalle marca `affects_inventory` para devolver repuestos al almacén en el flujo de la ODS. Aquí se generaliza, reutilizando las series, el driver electrónico y los ledgers.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Cajero / Vendedor | `user` con caja | JWT · `credit_notes.view` |
| Jefe de ventas | `manager` | JWT · `credit_notes.create|cancel` |

## 3. User Stories (formato EARS)
### US-1 — Emitir una nota de crédito por devolución
**Como** jefe de ventas **quiero** emitir una nota de crédito por la devolución total o parcial de una venta **para** devolver el stock y corregir el comprobante.
**Cuando** se envía `POST /api/v1/sales/credit-notes` con `{ sale_id, document_series_id, reason_code: '06'|'07', reason_description, warehouse_id?, items: [{ sale_item_id, quantity, return_to_stock }], settlement }`, **el sistema debe** emitir la nota con el correlativo de su serie, devolver al stock las líneas marcadas (motivo `SALE_RETURN`, al costo unitario original de la venta) y aplicar el monto según `settlement`.
**Módulo:** `Sales` (`/api/v1/sales/credit-notes`)
**Permisos:** `credit_notes.create`
**Criterios de aceptación:**
- [ ] La venta está `issued`; la serie es de tipo `credit_note`, de la sede activa y coherente con el comprobante (factura → serie `F…`, boleta → `B…`, nota de venta → otra letra) → 422 si no.
- [ ] `06` (devolución total) toma todas las líneas pendientes; `07` (por ítem) acepta cantidades ≤ lo vendido menos lo ya devuelto en notas no anuladas → 422 si excede.
- [ ] Los montos por línea usan el precio unitario y el descuento originales de la venta (no editables en `06`/`07`).
- [ ] `return_to_stock` solo para ítems con `track_stock`.

### US-2 — Nota de crédito por descuento o disminución de valor
**Como** jefe de ventas **quiero** emitir una nota de crédito por un descuento posterior o una disminución de valor **para** reconocer una rebaja sin devolución.
**Cuando** se envía `POST /api/v1/sales/credit-notes` con `reason_code: '04'|'05'|'09'` y montos (`amount` global para `04`; por línea para `05`/`09`), **el sistema debe** emitir la nota sin movimientos de stock.
**Módulo:** `Sales`
**Permisos:** `credit_notes.create`
**Criterios de aceptación:**
- [ ] El total de la nota ≤ total de la venta − Σ notas de crédito vigentes → 422 si excede.
- [ ] Los impuestos se recalculan con la tasa guardada en la venta (`DocumentTotalsCalculator`).

### US-3 — Anulación total de la operación
**Como** jefe de ventas **quiero** anular por completo una venta ya aceptada por SUNAT **para** revertir la operación.
**Cuando** se envía `POST /api/v1/sales/credit-notes` con `reason_code: '01'`, **el sistema debe** emitir una nota por el total pendiente de la venta, devolver todo el stock de las líneas con `track_stock` y aplicar el monto según `settlement`.
**Módulo:** `Sales`
**Permisos:** `credit_notes.create`
**Criterios de aceptación:**
- [ ] Solo si no existe otra nota de crédito vigente sobre la venta → 422 en otro caso.

### US-4 — Aplicación del monto: rebaja de deuda o reembolso
**Como** cajero **quiero** que la nota reduzca primero lo que el cliente debe y reembolse el resto **para** no devolver dinero de más.
**Cuando** se emite una nota con `settlement: { mode: 'auto'|'refund', payment_method_id?, cash_session_id?, reference? }`, **el sistema debe**: si la venta tiene `balance > 0`, aplicar a ese saldo hasta agotarlo (reduciendo las cuotas pendientes desde la última); el remanente se reembolsa con `MoneySettlementService` (salida, concepto `SALE_REFUND`). Con `mode = refund` se reembolsa todo sin tocar el saldo.
**Módulo:** `Sales`
**Permisos:** `credit_notes.create`
**Criterios de aceptación:**
- [ ] Reembolso en caja: sesión abierta y efectivo disponible (`availableCash`) → 422 si no.
- [ ] La nota guarda `applied_to_balance` y `refunded_amount`, y `applied_to_balance + refunded_amount = total`.
- [ ] El estado de cuenta del cliente (spec 028) muestra la nota como abono.

### US-5 — Comprobante, facturación electrónica, consulta y anulación
**Como** jefe de ventas **quiero** imprimir, enviar y consultar notas de crédito, y anular una mal emitida **para** completar el ciclo.
**Cuando** se solicita `GET /api/v1/sales/credit-notes[/{id}]`, `GET /api/v1/sales/credit-notes/{id}/pdf?format=`, o se envía `POST /api/v1/sales/credit-notes/{id}/cancel` con `{ reason }`, **el sistema debe** listar o mostrar las notas, generar el PDF (con el comprobante que modifica, motivo y sustento) o anularla revirtiendo el stock, la rebaja de saldo y el reembolso.
**Módulo:** `Sales`
**Permisos:** `credit_notes.view`, `credit_notes.cancel`
**Criterios de aceptación:**
- [ ] Series electrónicas: se encola `SendElectronicDocumentJob` (spec 027) con la nota; con el driver nulo queda `not_applicable`.
- [ ] Anular una nota aceptada por SUNAT → 422; con el reembolso en una sesión de caja cerrada → 422.
- [ ] Una venta con notas de crédito vigentes no se anula (activa el hook `hasCreditNotes()` del spec 027).
- [ ] UI: `/sales/credit-notes` (listado) y acción "Emitir nota de crédito" en el detalle de la venta (`/sales/credit-notes/new?sale_id=`).

## 4. Reglas de negocio e invariantes
- Σ totales de notas vigentes de una venta ≤ total de la venta; Σ cantidades devueltas por línea ≤ cantidad vendida.
- `sale.balance` se reduce solo por el monto `applied_to_balance`; nunca queda negativo.
- La devolución al stock se valoriza al costo unitario de la línea original de la venta (no al costo promedio actual).
- Numeración por serie de tipo `credit_note` con `DocumentSeriesService::next()`.
- Las notas no se editan; se anulan.

## 5. Requisitos no funcionales
- Emitir una nota de 30 líneas en < 800 ms.
- Auditoría de emisión y anulación.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Notas de débito → spec futuro.
- Saldo a favor del cliente (crédito no reembolsado para compras futuras) → spec futuro.
- Motivos `02`, `03`, `08`, `10`–`13` del catálogo 09 → spec futuro.
- Notas de crédito de proveedores → spec futuro (Compras).
- Aprobación previa de notas de crédito (flujo de Multinort) → se controla con el permiso `credit_notes.create`.

## 7. Open Questions
- [ ] ¿Se permite devolver a un almacén distinto del de la venta? Propuesta: sí, de la misma sede. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `CreditNotesApiTest` y `CreditNoteSettlementTest` en verde.
- `inventory:verify-stock`, saldos de caja y `sale.balance` cuadran tras emitir y anular notas.
