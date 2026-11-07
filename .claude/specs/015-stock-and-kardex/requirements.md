# 015 · stock-and-kardex — Requirements
> **Spec:** 015 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 011, 014
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 2)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 014 — `CatalogItem`, `ItemPresentation`. Spec 011 — `ActiveBranch`. Spec 010 — `inventory.allow_negative_stock`.
> - Origen Multinort: `create_stocks_table.php` (+ `avg_cost`), `create_kardexes_table.php` (+ `unit_cost`, `total_cost`, `parent_id`, `is_open`), `Services/Tenant/Inventario/StockMovementService.php`, `KardexController.php`, `StockController.php`.
>
---
## 1. Contexto de Negocio
El inventario necesita saber **cuánto hay de cada ítem en cada almacén** y **a qué costo**, con un registro inmutable de cada entrada y salida (**kardex valorizado**) para auditoría, cálculo de márgenes y costo de ventas.

En Multinort, `StockMovementService` ya implementa `in`/`out` con `lockForUpdate` y **costo promedio ponderado** (`avg_cost = (q₀·c₀ + q·c) / (q₀ + q)`), y el kardex guarda cantidades antes/después, costo unitario y total. Sin embargo: el stock es **por presentación** (no por unidad base), existen stocks "abiertos" para fraccionar envases, la referencia es `reference_type`/`reference_id` enteros y el kardex usa `softDeletes` (no es inmutable).

Este spec crea un **ledger único** (`StockLedgerService`) por el que pasan todos los movimientos de los specs 016 (notas), 023 (recepción de compras), 027 (ventas) y 029 (notas de crédito), con stock en unidad base y kardex inmutable.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Jefe de almacén | `admin`, `manager` | JWT · `stock.view`, `stock.update_limits`, `kardex.view` |
| Vendedor / Comprador | `user` | JWT · `stock.view` (consulta de disponibilidad) |
| Módulos internos | Notas, compras, ventas, notas de crédito | `StockLedgerService` (sin HTTP) |

## 3. User Stories (formato EARS)
### US-1 — Registrar movimientos en el ledger (interno)
**Como** módulo de notas, compras o ventas **quiero** registrar entradas, salidas, traslados y reversiones **para** mantener stock y costo consistentes.
**Cuando** un módulo invoca `StockLedgerService::in|out|transfer|reverse`, **el sistema debe** bloquear la fila de stock, actualizar cantidad y costo promedio, y escribir un movimiento de kardex con saldos, todo en la transacción del documento de origen.
**Módulo:** `Inventory` (servicio interno)
**Permisos:** — (lo autoriza el endpoint del documento de origen)
**Criterios de aceptación:**
- [ ] Entrada: `avg_cost = (q₀·c₀ + q·c) / (q₀ + q)` con 6 decimales; si `q₀ ≤ 0`, `avg_cost = c`.
- [ ] Salida: valorizada al `avg_cost` vigente; el promedio no cambia.
- [ ] Si la salida deja stock negativo y `inventory.allow_negative_stock = false` → `InsufficientStockException` (422 "Stock insuficiente para <ítem> en <almacén>: disponible X, requerido Y").
- [ ] Traslado: salida del almacén origen y entrada en destino al mismo costo, enlazadas por `transfer_group_id`.
- [ ] Reversión: movimiento opuesto con el mismo costo unitario del original, enlazado por `reverses_id`; un movimiento solo se revierte una vez.
- [ ] Ítems con `track_stock = false` → `InventoryRuleException` (no se registran).
- [ ] Cada movimiento guarda `source_type`, `source_id` y `source_number` del documento que lo originó.

### US-2 — Consultar stock por almacén
**Como** jefe de almacén **quiero** ver existencias, costo promedio y valor por almacén **para** controlar el inventario.
**Cuando** se solicita `GET /api/v1/inventory/stock?warehouse_id=&category_id=&search=&below_min=&include_zero=`, **el sistema debe** listar el stock de los almacenes de la sede activa (o del almacén indicado, si es accesible).
**Módulo:** `Inventory` (`/api/v1/inventory/stock`)
**Permisos:** `stock.view`
**Criterios de aceptación:**
- [ ] Cada fila: `warehouse {id, code, name}`, `catalog_item {id, code, name, base_unit}`, `quantity`, `avg_cost`, `total_value = quantity × avg_cost`, `min_stock`, `max_stock`, `is_below_min`, `last_movement_at`.
- [ ] `below_min=1` filtra `quantity < min_stock`; por defecto se excluyen filas con `quantity = 0` salvo `include_zero=1`.
- [ ] `meta` incluye `total_value` del filtro.
- [ ] Almacén de una sede no accesible → 403.
- [ ] UI: `/inventory/stock` con filtros, chip rojo para bajo mínimo y valor total en el pie.

### US-3 — Stock de un ítem en todas las sedes
**Como** vendedor **quiero** ver en qué almacenes hay stock de un ítem **para** ofrecer alternativas al cliente.
**Cuando** se solicita `GET /api/v1/inventory/stock/by-item/{catalogItemId}`, **el sistema debe** devolver el stock del ítem por almacén en todas las sedes accesibles para el usuario, expresado en unidad base y en la presentación por defecto.
**Módulo:** `Inventory`
**Permisos:** `stock.view`
**Criterios de aceptación:**
- [ ] Incluye almacenes con stock 0 si existe fila de stock.
- [ ] El endpoint `lookup` del catálogo (spec 014) con `warehouse_id` agrega `stock.available` en unidad base y en la presentación devuelta.

### US-4 — Configurar mínimos y máximos
**Como** jefe de almacén **quiero** definir stock mínimo y máximo por ítem y almacén **para** detectar reposiciones.
**Cuando** se envía `PUT /api/v1/inventory/stock/limits` con `{ warehouse_id, catalog_item_id, min_stock, max_stock }`, **el sistema debe** crear o actualizar la fila de stock con esos límites sin alterar la cantidad.
**Módulo:** `Inventory`
**Permisos:** `stock.update_limits`
**Criterios de aceptación:**
- [ ] `min_stock ≥ 0`; `max_stock` nulo o `≥ min_stock` → si no, 422.

### US-5 — Kardex valorizado
**Como** jefe de almacén o contador **quiero** ver el kardex de un ítem en un almacén y un rango de fechas **para** auditar entradas, salidas y costo.
**Cuando** se solicita `GET /api/v1/inventory/kardex?warehouse_id=&catalog_item_id=&date_from=&date_to=`, **el sistema debe** devolver el saldo inicial al `date_from` y los movimientos del rango en orden cronológico con saldos acumulados.
**Módulo:** `Inventory` (`/api/v1/inventory/kardex`)
**Permisos:** `kardex.view`
**Criterios de aceptación:**
- [ ] Cada movimiento: `moved_at`, `movement_type`, `reason`, `source_number`, `source_type`, entrada (cantidad, costo unitario, total), salida (ídem), saldo (cantidad, costo promedio, total), `user`.
- [ ] `opening_balance { quantity, unit_cost, total }` calculado con el último movimiento anterior a `date_from`.
- [ ] Rango máximo de 366 días → 422 si se excede.
- [ ] `GET /api/v1/inventory/kardex/export?…&format=csv` descarga el mismo contenido en CSV (UTF-8 con BOM).
- [ ] UI: `/inventory/kardex` con selector de almacén, `CatalogItemPicker` (`context=stock`), rango de fechas y tabla con columnas agrupadas Entrada · Salida · Saldo.

## 4. Reglas de negocio e invariantes
- **Ledger único:** ningún código modifica `stocks.quantity` ni `stocks.avg_cost` fuera de `StockLedgerService`.
- **Kardex inmutable:** los movimientos no se actualizan ni se eliminan; los errores se corrigen con reversiones.
- `stocks.quantity` = suma de entradas − suma de salidas del kardex para ese (almacén, ítem). Se verifica con el comando `inventory:verify-stock`.
- El stock y el kardex se expresan en unidad base; la presentación se guarda solo como referencia.
- Los bloqueos de fila se toman en orden determinista (`warehouse_id`, `catalog_item_id`) para evitar deadlocks en documentos con varias líneas.
- Un ítem con movimientos no puede cambiar `base_unit_id`, `type` ni el `factor` de presentaciones usadas (activa las verificaciones previstas en el spec 014).
- Un almacén con stock distinto de cero o con movimientos no se elimina (activa la verificación del spec 013).

## 5. Requisitos no funcionales
- Registrar un documento de 50 líneas en el ledger toma < 500 ms.
- Consulta de kardex de un año para un ítem en < 300 ms (índice `warehouse_id, catalog_item_id, moved_at`).
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Notas de almacén (ingreso, salida, traslado, ajuste) y toma de inventario → spec 016.
- Reservas de stock por cotizaciones o pedidos → spec futuro.
- Lotes, series y fechas de vencimiento → spec futuro.
- Métodos de costeo PEPS/UEPS → spec futuro (solo promedio ponderado).
- Recalcular el kardex retroactivamente (movimientos con fecha pasada) → spec futuro; aquí `moved_at` es siempre la fecha de registro.
- Exportación a Excel/PDF → spec futuro (aquí CSV).

## 7. Open Questions
- [ ] ¿El costo promedio debe ser por almacén (propuesta) o por empresa? — Responsable: Luis Acuña.
- [ ] ¿Se requiere alerta (notificación o correo) al quedar bajo mínimo? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `StockLedgerServiceTest` cubre entrada, salida, promedio, negativo, traslado, reversión e inmutabilidad.
- `inventory:verify-stock` sin diferencias tras ejecutar toda la suite de inventario.
- `StockApiTest` y `KardexApiTest` en verde.
