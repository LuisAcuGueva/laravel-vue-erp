# 021 · providers — Requirements
> **Spec:** 021 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 012, 014, 017
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 4)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 012 — enum `DocumentType`. Spec 014 — presentaciones. Spec 017 — bancos y monedas.
> - Origen Multinort: `create_providers_table.php`, `create_price_providers_table.php`, `Proveedor/ProviderController.php`.
>
---
## 1. Contexto de Negocio
Compras necesita un **directorio de proveedores** con datos fiscales, contactos, cuentas bancarias (para pagarles) y condiciones comerciales (moneda y días de crédito), además de una **lista de precios por proveedor** que ayude a elegir a quién comprar.

En Multinort, `providers` guarda nombre, tipo y número de documento, email (único y obligatorio), teléfono, un único `number_count` (cuenta) y comentario; `price_providers` guarda el precio por presentación con vigencia. Aquí se separan contactos y cuentas bancarias y se agregan condiciones comerciales.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Comprador | `manager` o rol de compras | JWT · `providers.view|create|update|delete` |
| Tesorero | `manager` | JWT · `providers.view` (cuentas bancarias para pagar) |

## 3. User Stories (formato EARS)
### US-1 — CRUD de proveedores
**Como** comprador **quiero** registrar proveedores con sus datos fiscales y condiciones **para** emitir órdenes de compra y pagarles.
**Cuando** se invoca el CRUD `/api/v1/purchases/providers`, **el sistema debe** gestionar proveedores con `document_type` (`RUC`, `DNI`, `CE`, `PASSPORT`, `OTHER`), `document_number`, `business_name`, `trade_name`, `address`, `ubigeo`, `email`, `phone`, `website`, `default_currency_id`, `credit_days`, `notes` e `is_active`.
**Módulo:** `Purchases` (`/api/v1/purchases/providers`)
**Permisos:** `providers.view|create|update|delete`
**Criterios de aceptación:**
- [ ] `(document_type, document_number)` único entre proveedores no eliminados; RUC de 11 dígitos que empiece por 10, 15, 17 o 20 → 422 si no.
- [ ] `email` opcional (a diferencia de Multinort) y con formato válido.
- [ ] `credit_days` entre 0 y 365 (0 = contado).
- [ ] Listado con `search` (documento, razón social, nombre comercial), filtro `is_active`, paginado.
- [ ] Eliminar un proveedor con órdenes o facturas → 422 (verificaciones de los specs 022 y 024).
- [ ] UI: `/purchases/providers` (listado) y `/purchases/providers/[id]` (pestañas General · Contactos · Cuentas bancarias · Precios).

### US-2 — Contactos del proveedor
**Como** comprador **quiero** registrar varias personas de contacto **para** saber a quién escribir.
**Cuando** se envía `PUT /api/v1/purchases/providers/{id}/contacts` con `[{ id?, name, position, phone, email, is_primary }]`, **el sistema debe** sincronizar los contactos (crear, actualizar, eliminar los no enviados).
**Módulo:** `Purchases`
**Permisos:** `providers.update`
**Criterios de aceptación:**
- [ ] Como máximo un contacto `is_primary` → 422 si llegan dos.

### US-3 — Cuentas bancarias del proveedor
**Como** tesorero **quiero** ver las cuentas del proveedor **para** transferirle los pagos.
**Cuando** se envía `PUT /api/v1/purchases/providers/{id}/bank-accounts` con `[{ id?, bank_id, currency_id, account_number, cci, is_detraction_account }]`, **el sistema debe** sincronizar las cuentas.
**Módulo:** `Purchases`
**Permisos:** `providers.update`
**Criterios de aceptación:**
- [ ] CCI de 20 dígitos si se informa; número de cuenta único por (proveedor, banco).

### US-4 — Precios por proveedor
**Como** comprador **quiero** registrar el precio que me ofrece cada proveedor por presentación **para** compararlos al comprar.
**Cuando** se invoca `GET|POST /api/v1/purchases/providers/{id}/prices` y `PUT|DELETE /api/v1/purchases/provider-prices/{id}`, **el sistema debe** gestionar precios con `item_presentation_id`, `currency_id`, `price`, `valid_from`, `valid_until`, `notes`.
**Módulo:** `Purchases`
**Permisos:** `providers.update`
**Criterios de aceptación:**
- [ ] No se superponen vigencias para el mismo (proveedor, presentación, moneda) → 422.
- [ ] `GET /api/v1/purchases/provider-prices/compare?item_presentation_id=` devuelve los precios vigentes de todos los proveedores ordenados de menor a mayor (convertidos a moneda base para ordenar), y el último costo de compra (spec 023).
- [ ] La recepción de compras (spec 023) registra automáticamente el último precio pagado (`last_purchase_price`, `last_purchase_at`) por proveedor y presentación.

### US-5 — Búsqueda rápida de proveedores
**Como** comprador **quiero** buscar proveedores activos por documento o nombre **para** elegirlos en órdenes y facturas.
**Cuando** se solicita `GET /api/v1/purchases/providers/options?search=`, **el sistema debe** devolver hasta 20 proveedores activos `{ id, document_type, document_number, business_name, default_currency_id, credit_days }`.
**Módulo:** `Purchases`
**Permisos:** `providers.view`
**Criterios de aceptación:**
- [ ] Coincidencia exacta de documento primero.

## 4. Reglas de negocio e invariantes
- Un proveedor se identifica por (tipo, número de documento).
- Un solo contacto principal por proveedor.
- Las vigencias de precios de un mismo proveedor, presentación y moneda no se superponen.
- Un proveedor con documentos no se elimina; se desactiva, y los inactivos no aparecen en `options`.

## 5. Requisitos no funcionales
- Auditoría de proveedores, contactos, cuentas y precios.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Consulta de RUC/DNI en SUNAT/RENIEC → spec futuro.
- Evaluación y calificación de proveedores → spec futuro.
- Detracciones y retenciones → spec futuro (aquí solo se marca la cuenta de detracción).
- Órdenes de compra → spec 022; facturas y pagos → spec 024.

## 7. Open Questions
- [ ] ¿Los proveedores pueden ser también clientes (misma entidad)? Propuesta: no; son tablas separadas. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `ProvidersApiTest` y `ProviderPricesApiTest` en verde.
