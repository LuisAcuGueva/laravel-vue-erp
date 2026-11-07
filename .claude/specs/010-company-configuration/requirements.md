# 010 · company-configuration — Requirements
> **Spec:** 010 · **Alcance:** fullstack · **Módulo:** Configuration · **Prioridad:** alta · **Depende de specs:** 005, 007, 008
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 1)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Origen Multinort: `database/migrations/tenant/2026_02_12_222141_create_general_settings_table.php`, `2026_06_09_103637_create_system_parameters_table.php`.
>
---
## 1. Contexto de Negocio
Todo el ERP (comprobantes, cotizaciones en PDF, impuestos, descuentos, tolerancias de caja) necesita conocer **los datos de la empresa** y un conjunto de **parámetros de negocio** que el administrador pueda ajustar sin desplegar código.

En Multinort existe `general_settings` (razón social, RUC, ubigeo, logo, políticas, redes sociales, `tax_default` como texto) y `system_parameters` (pares `group_key`/`item_key` con valor JSON y vigencia, pensado para descuentos de cotización y márgenes de taller). Los dos están acoplados al rubro: `name_club` para membresías, `category_id` para márgenes por categoría y descuentos por campaña.

El Template hoy **no tiene ninguna configuración de empresa**. Este spec la crea de forma genérica: un registro único de empresa y un catálogo de parámetros tipados que los specs posteriores consumen (`sales.max_line_discount_percent`, `inventory.allow_negative_stock`, `finance.cash_difference_tolerance`, etc.).

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador | Rol `admin` | JWT Bearer · `configuration.view`, `configuration.update` |
| Usuario operativo | Cualquier usuario autenticado | JWT Bearer · lectura del resumen público de empresa (nombre, logo, moneda, impuesto) |
| Módulos internos | Services de Ventas, Inventario, Finanzas | Lectura vía `SystemParameterService` (sin HTTP) |

## 3. User Stories (formato EARS)
### US-1 — Consultar datos de la empresa
**Como** administrador **quiero** ver la ficha de la empresa **para** verificar los datos que salen en comprobantes y documentos.
**Cuando** se solicita `GET /api/v1/configuration/company`, **el sistema debe** devolver el registro único de empresa (lo crea con valores por defecto si aún no existe).
**Módulo:** `Configuration` (`/api/v1/configuration/company`)
**Permisos:** `configuration.view`
**Criterios de aceptación:**
- [ ] 200 con `{ "success": true, "data": { "id", "legal_name", "trade_name", "ruc", "address", "ubigeo", "phone", "email", "website", "logo_url", "default_currency_code", "default_tax_rate", "prices_include_tax", "terms_conditions", "warranty_policy", "social_links", "updated_at" } }`.
- [ ] Sin el permiso → 403. Sin token → 401.
- [ ] UI: pestaña "Empresa" en `/configuration` visible solo si `can('configuration.view')`.

### US-2 — Actualizar datos de la empresa
**Como** administrador **quiero** editar razón social, documento fiscal, dirección, impuesto y políticas **para** mantener los datos legales al día.
**Cuando** se envía `PUT /api/v1/configuration/company`, **el sistema debe** validar y persistir los cambios, registrando la auditoría (spec 007).
**Módulo:** `Configuration`
**Permisos:** `configuration.update`
**Criterios de aceptación:**
- [ ] `legal_name` obligatorio (≤ 200), `ruc` obligatorio y válido (11 dígitos, prefijo y dígito verificador SUNAT).
- [ ] `default_tax_rate` numérico entre 0 y 100 con 2 decimales; `default_currency_code` ISO 4217 de 3 letras mayúsculas.
- [ ] `ubigeo` opcional de 6 dígitos; `email` y `website` con formato válido.
- [ ] Errores → 422 `{ "message": "...", "errors": { "ruc": ["..."] } }`, mostrados bajo cada campo con `useApiFormErrors`.
- [ ] La actividad aparece en `/audit` con los atributos modificados.
- [ ] UI: botón "Guardar" deshabilitado sin `can('configuration.update')`.

### US-3 — Subir logo de la empresa
**Como** administrador **quiero** subir el logo **para** que aparezca en PDFs y en la cabecera de la app.
**Cuando** se envía `POST /api/v1/configuration/company/logo` (multipart, campo `logo`), **el sistema debe** guardar el archivo en el disco público, reemplazar el anterior y devolver la URL.
**Módulo:** `Configuration`
**Permisos:** `configuration.update`
**Criterios de aceptación:**
- [ ] Acepta `png`, `jpg`, `jpeg`, `webp`, `svg` hasta 2 MB; otro tipo → 422.
- [ ] El archivo anterior se elimina del disco.
- [ ] Respuesta 200 con `data.logo_url` absoluta.
- [ ] UI: vista previa del logo y estado `loading` durante la subida.

### US-4 — Consultar y editar parámetros de negocio
**Como** administrador **quiero** ver los parámetros agrupados (Ventas, Inventario, Finanzas) y cambiar sus valores **para** ajustar reglas sin desplegar código.
**Cuando** se solicita `GET /api/v1/configuration/parameters?group=<group>` o se envía `PUT /api/v1/configuration/parameters/{id}`, **el sistema debe** listar los parámetros o validar el nuevo valor contra su `value_type` y guardarlo.
**Módulo:** `Configuration` (`/api/v1/configuration/parameters`)
**Permisos:** `configuration.view` (listar), `configuration.update` (editar)
**Criterios de aceptación:**
- [ ] El listado devuelve `{ id, group_key, item_key, key, label, description, value_type, value, is_editable, is_active }`, donde `key = group_key.item_key`.
- [ ] `value` se valida según `value_type` (`string`, `integer`, `decimal`, `boolean`, `json`); un tipo incorrecto → 422.
- [ ] Un parámetro con `is_editable = false` → 422 "El parámetro no es editable".
- [ ] La API no permite crear ni eliminar parámetros: los registra `SystemParameterSeeder`.
- [ ] Rutas `{id}` con `whereUuid`; id inexistente → 404.
- [ ] UI: pestaña "Parámetros" con tabla agrupada por `group_key` y edición inline según el tipo (switch, número o texto).

### US-5 — Resumen de empresa para cualquier usuario autenticado
**Como** usuario autenticado **quiero** que la app conozca el nombre comercial, el logo, la moneda y el impuesto por defecto **para** mostrarlos en la cabecera y en formularios de venta.
**Cuando** se solicita `GET /api/v1/configuration/company/summary`, **el sistema debe** devolver solo `trade_name`, `legal_name`, `logo_url`, `default_currency_code`, `default_tax_rate` y `prices_include_tax`.
**Módulo:** `Configuration`
**Permisos:** autenticado
**Criterios de aceptación:**
- [ ] 200 para cualquier usuario autenticado, sin exponer `ruc` ni políticas.
- [ ] El front lo carga una vez tras el login y lo guarda en el store `configuration`.

## 4. Reglas de negocio e invariantes
- **Registro único:** existe como máximo una fila en `company_settings`; el servicio la crea perezosamente (`firstOrCreate`).
- **Parámetros identificados por clave:** `(group_key, item_key)` es único; el código lee siempre por clave (`sales.max_line_discount_percent`), nunca por id.
- **Valores tipados:** el valor almacenado siempre respeta `value_type`; la lectura desde servicios devuelve el tipo PHP nativo (`int`, `float`, `bool`, `array`, `string`).
- **Caché coherente:** la lectura de parámetros se cachea y la caché se invalida al actualizar un parámetro.
- **Parámetros iniciales** (seeder, idempotente): `sales.max_line_discount_percent` (decimal, 10), `sales.max_global_discount_percent` (decimal, 10), `sales.quotation_validity_days` (integer, 15), `inventory.allow_negative_stock` (boolean, false), `finance.cash_difference_tolerance` (decimal, 0.00).

## 5. Requisitos no funcionales
- Lectura de un parámetro desde servicio < 1 ms con la caché caliente (sin consulta SQL).
- Todos los cambios de `company_settings` y `system_parameters` quedan en el activity log (spec 007).
- Mensajes de validación en español; Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Catálogo de ubigeo (departamentos/provincias/distritos) con selects encadenados → spec futuro; aquí `ubigeo` es un código de 6 dígitos.
- Series y correlativos de comprobantes por sede → spec 027 (sales-and-vouchers).
- Monedas y tipos de cambio como maestros → spec 017 (finance-masters); aquí `default_currency_code` es texto ISO.
- Parámetros por categoría y con vigencia (márgenes y campañas de Multinort) → descartados; las promociones no se migran.
- Configuración por sede (`branch_settings` de Multinort) → spec 011 (datos de sede) y spec 027 (series).

## 7. Open Questions
- [ ] ¿El logo debe tener una versión para fondo oscuro? — Responsable: Luis Acuña.
- [ ] ¿Se necesitan más grupos de parámetros iniciales antes de la Fase 3? — Responsable: Luis Acuña (se amplían en cada spec consumidor).

## 8. Métricas de éxito
- `CompanySettingsApiTest`, `SystemParametersApiTest` y `SystemParameterServiceTest` en verde.
- Los specs 018, 026 y 027 leen sus reglas desde `SystemParameterService` sin constantes hardcodeadas.
- La pantalla `/configuration` permite editar empresa y parámetros sin errores de consola.
