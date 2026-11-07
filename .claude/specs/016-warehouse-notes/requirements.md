# 016 · warehouse-notes — Requirements
> **Spec:** 016 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 013, 014, 015
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 2)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 015 — `StockLedgerService`. Spec 013 — motivos y almacenes. Spec 014 — `SequentialCodeGenerator`, `CatalogItemPicker`.
> - Origen Multinort: `create_warehouse_notes_table.php`, `create_warehouse_note_details_table.php`, `Services/Tenant/Logistica/WarehouseNoteService.php`, `InventoryController.php` (`inventories`, `inventory_details`: conteo y finalización).
>
---
## 1. Contexto de Negocio
Fuera de compras y ventas, el almacén necesita registrar movimientos físicos propios: **carga inicial de stock**, **ingresos y salidas** por motivos internos (consumo, merma, donación, muestra), **traslados entre almacenes** y **ajustes por toma de inventario**.

En Multinort, `WarehouseNoteService` registra notas de `entrada`/`salida` con flujo `borrador → aprobado → cancelado`, número correlativo y costo por línea. La aprobación aplica el stock. No permite anular una nota aprobada: obliga a hacer una nota inversa a mano. No tiene traslados, y la toma de inventario (`inventories`) es otro módulo con conteo y "finalizar".

Este spec unifica los movimientos manuales en **notas de almacén** (ingreso, salida y traslado) con aprobación. Además agrega **anulación con reversión automática** de una nota aprobada y la **toma de inventario**, que al cerrarse genera las notas de ajuste.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Almacenero | `user` con permisos de almacén | JWT · `warehouse_notes.view|create|update` |
| Jefe de almacén | `manager` | JWT · `warehouse_notes.approve|cancel`, `inventory_counts.*` |

## 3. User Stories (formato EARS)
### US-1 — Registrar una nota de almacén en borrador
**Como** almacenero **quiero** registrar una nota de ingreso, salida o traslado con sus líneas **para** documentar un movimiento físico.
**Cuando** se envía `POST /api/v1/inventory/warehouse-notes`, **el sistema debe** crear la nota en estado `draft` con número correlativo por tipo (`NI-000001`, `NS-000001`, `NT-000001`), sin afectar el stock.
**Módulo:** `Inventory` (`/api/v1/inventory/warehouse-notes`)
**Permisos:** `warehouse_notes.create`
**Criterios de aceptación:**
- [ ] Campos: `type` (`in`|`out`|`transfer`), `warehouse_id` (de la sede activa), `target_warehouse_id` (obligatorio y distinto solo si `type = transfer`; puede ser de otra sede accesible), `reason_id` (del mismo tipo o de tipo `adjustment` para `in`/`out`; nunca un motivo de sistema salvo `OPENING`), `document_date`, `reference_document`, `observation`, `items[]`.
- [ ] Cada línea: `item_presentation_id`, `quantity > 0` (entera si la unidad no admite decimales), `unit_cost` obligatorio y `≥ 0` solo en notas `in` (costo por presentación), `notes`.
- [ ] Ítems de tipo servicio o sin `track_stock` → 422.
- [ ] Respuesta 201 con la nota, `base_quantity` calculada por línea y `total_cost` estimado.
- [ ] UI: formulario `/inventory/warehouse-notes/new` con `CatalogItemPicker` (`context=stock`, muestra stock disponible), grilla de líneas y totales.

### US-2 — Editar y aprobar la nota
**Como** jefe de almacén **quiero** revisar y aprobar notas **para** que el stock se actualice solo con movimientos validados.
**Cuando** se envía `PUT /api/v1/inventory/warehouse-notes/{id}` (solo `draft`) o `POST /api/v1/inventory/warehouse-notes/{id}/approve`, **el sistema debe** actualizar la nota, o aprobarla aplicando los movimientos en el ledger en una sola transacción.
**Módulo:** `Inventory`
**Permisos:** `warehouse_notes.update` (editar), `warehouse_notes.approve` (aprobar)
**Criterios de aceptación:**
- [ ] Editar o aprobar una nota que no está en `draft` → 422 "Solo se pueden modificar notas en borrador".
- [ ] Aprobar una salida o traslado sin stock suficiente → 422 con el detalle del ítem (`InsufficientStockException`) y la nota sigue en `draft`.
- [ ] Al aprobar: `in` → entradas al costo indicado (`unit_cost / factor` por unidad base); `out` → salidas al costo promedio; `transfer` → traslados. Las líneas guardan el costo real aplicado y la nota su `total_cost`.
- [ ] Si el motivo tiene `requires_approval = false`, el creador puede enviar `approve: true` en el `POST` para registrar y aprobar en un paso con `warehouse_notes.create`.
- [ ] Se registran `approved_by` y `approved_at`.

### US-3 — Anular una nota
**Como** jefe de almacén **quiero** anular una nota en borrador o aprobada **para** corregir errores sin editar el kardex.
**Cuando** se envía `POST /api/v1/inventory/warehouse-notes/{id}/cancel` con `{ reason }`, **el sistema debe** marcarla `cancelled`; si estaba aprobada, debe revertir cada movimiento de kardex de la nota con `StockLedgerService::reverse()`.
**Módulo:** `Inventory`
**Permisos:** `warehouse_notes.cancel`
**Criterios de aceptación:**
- [ ] `reason` obligatorio (≥ 5 caracteres).
- [ ] Revertir una entrada cuyo stock ya se consumió y dejaría negativo (con el parámetro en `false`) → 422 y la nota sigue `approved`.
- [ ] Nota ya anulada → 422.
- [ ] Las notas generadas por una toma de inventario solo se anulan anulando la toma (→ 422 en caso contrario).

### US-4 — Consultar notas
**Como** almacenero **quiero** listar y ver notas **para** hacer seguimiento.
**Cuando** se solicita `GET /api/v1/inventory/warehouse-notes` o `GET /api/v1/inventory/warehouse-notes/{id}`, **el sistema debe** listar las notas de los almacenes de la sede activa con filtros `type`, `status`, `warehouse_id`, `reason_id`, `date_from`, `date_to` y `search` (número o referencia), o devolver el detalle con líneas, usuarios y movimientos de kardex.
**Módulo:** `Inventory`
**Permisos:** `warehouse_notes.view`
**Criterios de aceptación:**
- [ ] Un traslado aparece tanto en la sede de origen como en la de destino.
- [ ] UI: `/inventory/warehouse-notes` con chips de estado y acciones según estado y permisos (Editar, Aprobar, Anular).

### US-5 — Toma de inventario
**Como** jefe de almacén **quiero** contar físicamente un almacén (completo o por categoría) y ajustar las diferencias **para** que el stock del sistema coincida con la realidad.
**Cuando** se crea `POST /api/v1/inventory/counts` con `{ warehouse_id, category_id?, count_date, notes? }`, se registran conteos `PUT /api/v1/inventory/counts/{id}/items` y se cierra `POST /api/v1/inventory/counts/{id}/close`, **el sistema debe** generar las líneas a contar, guardar las cantidades contadas y, al cerrar, crear y aprobar las notas de ajuste (`COUNT_ADJUSTMENT_IN` y `COUNT_ADJUSTMENT_OUT`) por la diferencia entre lo contado y el stock en ese momento.
**Módulo:** `Inventory` (`/api/v1/inventory/counts`)
**Permisos:** `inventory_counts.view`, `inventory_counts.manage` (crear, contar), `inventory_counts.close` (cerrar y anular)
**Criterios de aceptación:**
- [ ] Al crear: líneas para todos los ítems con fila de stock en el almacén (filtrados por categoría y sus subcategorías), con `system_quantity` como referencia; estado `counting`; número `TI-000001`.
- [ ] Se pueden agregar ítems sin fila de stock (conteo de algo no registrado).
- [ ] Solo un conteo `counting` por almacén → 422.
- [ ] Cerrar con líneas sin contar → 422, salvo `treat_uncounted_as_zero: true`.
- [ ] Al cerrar: diferencia = `counted − stock actual`; positivas → nota `in` valorizada al costo promedio vigente (o al `last_purchase_cost` si el promedio es 0); negativas → nota `out`. Ambas aprobadas y enlazadas a la toma. Estado `closed`.
- [ ] Anular una toma cerrada anula sus notas (con reversión); una toma `counting` se anula sin efectos.
- [ ] UI: `/inventory/counts` (listado) y `/inventory/counts/[id]` con grilla de conteo (búsqueda y escaneo de código de barras), progreso contado/total y resumen de diferencias valorizadas antes de cerrar.

## 4. Reglas de negocio e invariantes
- Solo las notas `approved` afectan el stock; todo efecto pasa por `StockLedgerService`.
- Transiciones válidas: `draft → approved`, `draft → cancelled`, `approved → cancelled` (con reversión). Ninguna otra.
- Una nota aprobada tiene exactamente un movimiento de kardex por línea (dos en traslados) y su anulación, una reversión por movimiento.
- El número de la nota es único por tipo y no se reutiliza aunque la nota se anule.
- La sede de la nota es la del almacén de origen.

## 5. Requisitos no funcionales
- Aprobar una nota de 100 líneas en < 1 s.
- Auditoría de cabeceras de nota y toma (spec 007).
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- PDF imprimible de la nota → el spec 022 introduce la infraestructura PDF; las notas la reutilizan en un spec futuro.
- Traslados en dos pasos (despacho y recepción con mercadería en tránsito) → spec futuro.
- Ingresos por compra → spec 023; salidas por venta → spec 027; devoluciones de cliente → spec 029.
- Despachos de reparación, herramientas y preparaciones del taller → descartados.

## 7. Open Questions
- [ ] ¿Bloquear notas y ventas del almacén mientras hay una toma en `counting`? Propuesta: solo advertencia en la UI. — Responsable: Luis Acuña.
- [ ] ¿Se requiere doble conteo (dos personas) en la toma? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `WarehouseNotesApiTest` e `InventoryCountsApiTest` en verde.
- `inventory:verify-stock` sin diferencias tras aprobar y anular notas en los tests.
