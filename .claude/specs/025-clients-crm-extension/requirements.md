# 025 · clients-crm-extension — Requirements
> **Spec:** 025 · **Alcance:** fullstack · **Módulo:** Clients · **Prioridad:** media · **Depende de specs:** 003, 012
> **Status:** Done
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 5)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST (bloque `clients` sin `permission:`).
> - [Clients module](../../../back-laravel/app/Modules/Clients/) — CRUD mínimo actual (`name`, `description`).
> - Origen Multinort: `create_clients_table.php` (+ `add_dni_repesentative_to_clients`, `add_password_to_clients_table`).
>
---
## 1. Contexto de Negocio
Ventas necesita clientes con **datos fiscales** para emitir facturas y boletas (tipo y número de documento, razón social, dirección), **datos de contacto** y **condiciones comerciales**: límite y días de crédito, y tipo de precio por defecto (minorista o mayorista).

El módulo `Clients` del Template es un ejemplo mínimo: solo tiene `name` y `description` (`back-laravel/app/Modules/Clients/Models/Client.php`), y sus rutas en `routes/api.php` **no aplican `permission:clients.*`**, de modo que cualquier usuario autenticado puede crear o eliminar clientes desde la API (solo el frontend oculta los botones). En Multinort, `clients` tiene tipo de persona, documento, nombres y apellidos, representante, ubigeo por tablas, email y observaciones, más una contraseña para la app de clientes (fuera de alcance).

Este spec amplía el modelo sin romper los datos existentes, corrige la autorización y agrega el cliente genérico "CLIENTES VARIOS" para ventas sin identificar.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Vendedor / Cajero | `user` | JWT · `clients.view|create|update` |
| Administrador de ventas | `manager` | JWT · `clients.*`, `clients.manage_credit` |

## 3. User Stories (formato EARS)
### US-1 — Autorización real en la API de clientes
**Como** administrador **quiero** que la API de clientes exija los permisos `clients.*` **para** que la seguridad no dependa del frontend.
**Cuando** se invoca cualquier endpoint `/api/v1/clients`, **el sistema debe** exigir `clients.view` (listar y ver), `clients.create`, `clients.update` o `clients.delete` según la acción.
**Módulo:** `Clients` (`/api/v1/clients`)
**Permisos:** `clients.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Un usuario sin el permiso recibe 403 en la acción correspondiente (hoy recibe 200/201/204).
- [ ] `ClientsApiTest` actualizado con casos 403.

### US-2 — Datos fiscales, de contacto y comerciales
**Como** vendedor **quiero** registrar el documento, la razón social, la dirección y el contacto del cliente **para** emitir comprobantes válidos.
**Cuando** se crea o actualiza un cliente, **el sistema debe** aceptar y validar `client_type` (`person`|`company`), `document_type` (`RUC`, `DNI`, `CE`, `PASSPORT`, `OTHER`), `document_number`, `name` (razón social o nombre completo), `trade_name`, `representative_name`, `representative_document`, `email`, `phone`, `address`, `ubigeo`, `default_price_type` (`retail`|`wholesale`), `description` (observaciones) e `is_active`.
**Módulo:** `Clients`
**Permisos:** `clients.create|update`
**Criterios de aceptación:**
- [ ] `(document_type, document_number)` único entre clientes no eliminados; validación por tipo con `DocumentType::rulesFor()` (RUC de 11 dígitos con prefijo válido, DNI de 8).
- [ ] `client_type = company` exige `document_type = RUC`; un RUC que empieza por 10 corresponde a persona con negocio y se acepta con `client_type = person`.
- [ ] Los clientes existentes (solo `name`) siguen siendo válidos: `document_type`/`document_number` son opcionales en la base de datos y obligatorios solo en altas nuevas y al editar.
- [ ] Listado con `search` (documento, nombre, nombre comercial, email), filtros `client_type`, `is_active`, paginado.

### US-3 — Condiciones de crédito
**Como** administrador de ventas **quiero** fijar el límite y los días de crédito de cada cliente **para** controlar las ventas al crédito (spec 027).
**Cuando** se envía `PUT /api/v1/clients/{id}/credit` con `{ credit_limit, credit_days }`, **el sistema debe** actualizar las condiciones.
**Módulo:** `Clients`
**Permisos:** `clients.manage_credit`
**Criterios de aceptación:**
- [ ] `credit_limit ≥ 0` (0 = sin crédito); `credit_days` entre 0 y 365.
- [ ] Estos campos se ignoran si llegan en el `PUT /clients/{id}` general.
- [ ] El detalle del cliente muestra la deuda vigente y el crédito disponible cuando exista el spec 028 (hasta entonces, deuda = 0).

### US-4 — Cliente genérico y búsqueda rápida
**Como** cajero **quiero** buscar clientes por documento o nombre y usar "CLIENTES VARIOS" en ventas menores **para** vender rápido.
**Cuando** se solicita `GET /api/v1/clients/options?search=`, **el sistema debe** devolver hasta 20 clientes activos `{ id, client_type, document_type, document_number, name, address, default_price_type, credit_limit, is_system }`, con el cliente genérico primero si `search` está vacío.
**Módulo:** `Clients`
**Permisos:** `clients.view`
**Criterios de aceptación:**
- [ ] El seeder crea el cliente de sistema `CLIENTES VARIOS` (`document_type = OTHER`, `document_number = 00000000`, `is_system = true`), que no se edita ni se elimina → 422.
- [ ] Coincidencia exacta de documento primero.
- [ ] UI: `components/common/ClientPicker.vue` con alta rápida (diálogo con documento, nombre y dirección) sin salir del POS.

### US-5 — Contactos del cliente
**Como** vendedor **quiero** registrar personas de contacto de clientes empresa **para** enviar cotizaciones a la persona correcta.
**Cuando** se envía `PUT /api/v1/clients/{id}/contacts` con `[{ id?, name, position, phone, email, is_primary }]`, **el sistema debe** sincronizar los contactos.
**Módulo:** `Clients`
**Permisos:** `clients.update`
**Criterios de aceptación:**
- [ ] Como máximo un contacto principal → 422 si llegan dos.

## 4. Reglas de negocio e invariantes
- Un cliente se identifica por (tipo, número de documento) cuando tiene documento.
- El cliente de sistema existe siempre y es único.
- Un cliente con documentos de venta no se elimina (verificación agregada por los specs 026–028); se desactiva.
- `credit_limit` y `credit_days` solo cambian con `clients.manage_credit`.

## 5. Requisitos no funcionales
- `options` en < 100 ms con 50 000 clientes (índices por documento y `lower(name)`).
- Migración compatible con datos existentes (sin pérdida de `name`/`description`).
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Consulta de RUC/DNI en SUNAT/RENIEC → spec futuro.
- Varias direcciones de entrega por cliente → spec futuro.
- Portal o app de clientes (contraseña de Multinort) → descartado.
- Segmentación, campañas y CRM avanzado → spec futuro.
- Deuda y estado de cuenta → spec 028.

## 7. Open Questions
- [ ] ¿Se debe impedir vender al crédito a "CLIENTES VARIOS"? Propuesta: sí (crédito 0 fijo). — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `ClientsApiTest` ampliado (403 por acción, documento, crédito, sistema, contactos) en verde.
- Ningún endpoint de `/api/v1/clients` sin `permission:` (revisión de `route:list`).
