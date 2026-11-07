# 030 · ubigeo-peru — Requirements
> **Spec:** 030 · **Alcance:** fullstack · **Módulo:** Ubigeo · **Prioridad:** alta · **Depende de specs:** 005, 008
> **Status:** Done
> **Última actualización:** 2026-10-16
> **Autores:** Luis Acuña Guevara
> **Origen:** Requerimiento transversal: catálogo de ubigeo INEI y selector Departamento / Provincia / Distrito reutilizable
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md): arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/): reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php): endpoints de la API REST.
> - Specs que hoy guardan `ubigeo` como código libre de 6 dígitos: 010 (`company_settings`), 011 (`branches`), 021 (`providers`), 025 (`clients`).
>
---
## 1. Contexto de Negocio
Los comprobantes electrónicos SUNAT (spec 027), las direcciones de empresa, sedes, proveedores y clientes necesitan un **ubigeo** válido: el código INEI de 6 dígitos `DDPPdd` (departamento, provincia, distrito). Hoy cada spec lo trata como texto libre:

- 010, 011, 021 y 025 validan `ubigeo` solo con `digits:6`. Se acepta `999999` y el usuario tiene que saber el código de memoria.
- 010 y 011 dejaron explícitamente "Catálogo de ubigeo → spec futuro" en su Out of Scope.
- En el árbol de trabajo hay una migración sin commitear (`2026_10_16_000006_add_location_fields_to_company_settings_table.php`) que agrega `department`, `province` y `district` como texto libre a `company_settings`. Esa información duplica la del código y puede contradecirlo (ej. `ubigeo = 150101` con `district = "Miraflores"`).

Este spec crea el **catálogo oficial de ubigeo del Perú** en la base de datos y un **componente único de frontend** para elegir la ubicación: un solo campo que busca por distrito, provincia, departamento o código. Desde este spec, todo formulario que pida ubicación usa ese componente y toda tabla guarda solo el código.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario autenticado | Cualquier usuario con sesión (admin, cajero, vendedor, comprador…) | JWT · sin permiso Spatie adicional: el catálogo es de solo lectura y no es sensible |
| Desarrollador / despliegue | Quien instala o actualiza el sistema | Ejecuta el seeder del catálogo; no hay UI de mantenimiento |

## 3. User Stories (formato EARS)
### US-1 — Catálogo oficial de ubigeo cargado
**Como** responsable del despliegue **quiero** que la base de datos tenga el catálogo completo de ubigeos INEI **para** validar y mostrar ubicaciones sin depender de servicios externos.
**Cuando** se ejecutan las migraciones y el seeder del catálogo, **el sistema debe** registrar un ubigeo por distrito con su código de 6 dígitos y los nombres de departamento, provincia y distrito.
**Módulo:** `Ubigeo` (sin endpoint)
**Permisos:** no aplica
**Criterios de aceptación:**
- [x] Tras sembrar hay 25 departamentos distintos (Callao cuenta como departamento `07`), 196 provincias distintas y tantos distritos como filas tenga el archivo fuente versionado en el repo.
- [x] Ejecutar el seeder dos veces no duplica filas ni falla (upsert por `code`).
- [x] Si el archivo fuente cambia un nombre, el siguiente seed lo actualiza sin cambiar el `id` de la fila.
- [x] El seeder corre en `make` de desarrollo (`DatabaseSeeder`) y está documentado para producción.
- [x] Ningún dato se descarga de internet en tiempo de ejecución ni de seed.

### US-2 — Consultar el catálogo desde el frontend
**Como** usuario autenticado **quiero** que el frontend obtenga el catálogo de ubigeos **para** buscar y elegir ubicaciones sin esperar al servidor en cada tecla.
**Cuando** se solicita `GET /api/v1/ubigeo`, **el sistema debe** devolver la lista completa de distritos con `code`, `department`, `province` y `district`, ordenada por `code`.
**Módulo:** `Ubigeo` (`/api/v1/ubigeo`)
**Permisos:** autenticado
**Criterios de aceptación:**
- [x] 200 `{ "success": true, "data": [{ "code": "150122", "department": "LIMA", "province": "LIMA", "district": "MIRAFLORES" }, …], "meta": { "version": "<hash>" } }`.
- [x] Sin token → 401.
- [x] La respuesta lleva `ETag` y cabeceras de caché; una petición con `If-None-Match` igual responde 304 sin cuerpo.
- [x] El frontend la pide **una vez por carga de la aplicación** como máximo (las recargas posteriores revalidan con `ETag`), sin importar cuántos componentes de ubigeo haya en pantalla.

### US-3 — Componente de ubigeo: un solo campo con búsqueda
**Como** usuario que llena una dirección **quiero** un único campo donde escribir un distrito, una provincia, un departamento o un código **para** registrar la ubicación sin conocer el código ni recorrer varios selects.
**Cuando** el usuario escribe en el campo de ubigeo, **el sistema debe** listar los distritos que coinciden, cada uno como "Departamento, Provincia, Distrito · código", y al elegir uno el valor del campo pasa a ser su código de 6 dígitos; el componente conserva internamente departamento, provincia, distrito y código.
**Módulo:** Transversal (frontend)
**Permisos:** autenticado
**Criterios de aceptación:**
- [x] Es un solo campo (`AppAutocomplete`); no hay selects separados de departamento, provincia y distrito.
- [x] El valor (`v-model`) es solo el código de 6 dígitos o `null`; el detalle `{ code, department, province, district }` se obtiene con el evento `change` o con `selected` (expuesto por el componente).
- [x] Con un valor inicial (ej. `"150122"`), el campo muestra "Lima, Lima, Miraflores · 150122" sin intervención del usuario.
- [x] Con un valor inicial que no está en el catálogo, el campo muestra el error "Ubigeo no válido".
- [x] Mientras carga el catálogo, el campo está en `loading` y deshabilitado; si la carga falla, muestra "No se pudo cargar el catálogo de ubigeo" con opción de reintentar.
- [x] Limpiar el campo deja el valor en `null`.

### US-4 — Reglas de la búsqueda del componente
**Como** usuario **quiero** que la búsqueda sea tolerante y ordenada **para** encontrar el distrito correcto rápido.
**Cuando** el usuario escribe en el campo, **el sistema debe** buscar solo distritos vigentes por su nombre, el de su provincia, el de su departamento o su código.
**Módulo:** Transversal (frontend)
**Permisos:** autenticado
**Criterios de aceptación:**
- [x] Ignora mayúsculas, tildes y diéresis: `"ancon"` encuentra `ANCÓN`; `"huanuco"` encuentra los distritos de `HUÁNUCO`.
- [x] Busca desde el segundo carácter y muestra como máximo 50 resultados.
- [x] Cada palabra escrita debe coincidir con el distrito, la provincia, el departamento o el código: `"miraflores lima"` encuentra solo `150122`; `"miraflores arequipa"` encuentra el de Camaná.
- [x] Orden: distritos cuyo nombre empieza con el texto, luego los que lo contienen, luego los que coinciden por provincia y por departamento; dentro de cada grupo, alfabético.
- [x] Si el texto son solo dígitos, busca por prefijo de código (`"1501"` → distritos de la provincia Lima).
- [x] Al escribir un código exacto de 6 dígitos que existe, lo selecciona directamente; uno inexistente muestra "No se encontró el ubigeo 999999" y no cambia el valor.
- [x] Teclado: flechas para recorrer resultados, Enter para elegir, Escape para cerrar (nativo de `VAutocomplete`).

### US-5 — Validación de ubigeo en la API
**Como** sistema **quiero** rechazar códigos que no existen en el catálogo **para** no guardar ubicaciones inválidas.
**Cuando** un endpoint recibe un campo de ubigeo, **el sistema debe** aceptar solo `null` (si es opcional) o un código que exista en el catálogo.
**Módulo:** Transversal (`company_settings`, `branches` y todo módulo futuro con ubigeo)
**Permisos:** los del recurso
**Criterios de aceptación:**
- [x] `PUT /api/v1/configuration/company` y `POST|PUT /api/v1/branches` con `ubigeo: "999999"` → 422 `{ "message": "...", "errors": { "ubigeo": ["El ubigeo seleccionado no es válido."] } }`.
- [x] Con un código existente → 200/201 y se guarda solo el código.
- [x] El error 422 se muestra bajo el componente de ubigeo del formulario (mecanismo de errores del spec 008).

### US-6 — Mostrar la ubicación a partir del código
**Como** usuario **quiero** ver "Lima, Lima, Miraflores" en lugar de "150122" en listados, detalles y documentos **para** entender la ubicación sin buscarla.
**Cuando** una pantalla o un documento necesita mostrar una ubicación guardada, **el sistema debe** obtener los nombres desde el catálogo a partir del código.
**Módulo:** Transversal
**Permisos:** los del recurso
**Criterios de aceptación:**
- [x] En el frontend existe una forma única de convertir un código en texto legible, que usa el catálogo ya cargado (sin peticiones adicionales por fila).
- [x] En el backend existe un servicio que devuelve departamento, provincia y distrito de un código, para PDFs y exportaciones (spec 027), sin una consulta por cada fila del documento.
- [x] Un código nulo o inexistente se muestra como "—", sin error.

### US-7 — Adoptar el componente en los formularios existentes
**Como** usuario **quiero** el mismo selector de ubicación en la configuración de empresa y en sedes **para** no escribir códigos a mano.
**Cuando** se abre el formulario de empresa (`/configuration/company`) o el drawer de sedes (`/maintenance/branches`), **el sistema debe** mostrar el componente de ubigeo en lugar del campo de texto de 6 dígitos.
**Módulo:** `Configuration`, `Maintenance`
**Permisos:** `configuration.update`, `branches.create`, `branches.update`
**Criterios de aceptación:**
- [x] `company_settings` no guarda nombres de departamento, provincia ni distrito. Las columnas de texto de la migración sin commitear `2026_10_16_000006_…` se retiran antes de entrar a `master`; `CompanySettingRequest`, `CompanySettingResource`, el modelo y `CompanyForm.vue` vuelven a manejar solo `ubigeo`.
- [x] El listado de sedes muestra la ubicación legible (US-6).
- [x] Los specs en Draft que usan `ubigeo` (021 proveedores, 025 clientes) quedan anotados para usar el componente y la regla de validación.

## 4. Reglas de negocio e invariantes
- El ubigeo de una entidad se guarda **solo** como código INEI de 6 dígitos (`char(6)`, `ubigeo` o `<prefijo>_ubigeo`). Nunca se guardan los nombres de departamento, provincia o distrito en tablas de dominio; se derivan del catálogo.
- El valor válido siempre es un **distrito**: un departamento o una provincia sin distrito no es un ubigeo completo.
- El código se compone así: departamento = 2 primeros dígitos, provincia = 4 primeros, distrito = 6. El catálogo es consistente con esa jerarquía.
- Fuente única: el catálogo INEI versionado en el repo. Se usa el código INEI (el mismo que exige SUNAT en el UBL 2.1), no el de RENIEC.
- El catálogo es de solo lectura para la aplicación: no hay endpoints para crearlo, editarlo ni borrarlo; los cambios entran por el archivo fuente y el seeder.
- Ningún registro del catálogo se elimina si algún código está en uso; si INEI retira un código, se marca como no vigente en vez de borrarlo (ver Open Questions).

## 5. Requisitos no funcionales
- `GET /api/v1/ubigeo` responde en < 200 ms con la caché del servidor caliente; el cuerpo comprimido pesa < 60 KB.
- La búsqueda del componente se resuelve en el navegador, en < 50 ms por tecla con el catálogo completo.
- El componente es accesible: labels asociados, navegación por teclado y mensajes de error leídos por lectores de pantalla.
- Feature tests del endpoint, del seeder y de la regla de validación; pruebas unitarias (Vitest) de la normalización y del ranking de la búsqueda.
- Pint, Larastan, `make test`, `npm run lint` y `npm run test` en verde.

## 6. Out of Scope
- Ubigeo de países distintos de Perú y direcciones internacionales → spec futuro (si llega a haber clientes o proveedores del exterior, spec 021 / 025 definen un campo `country`).
- Geolocalización, mapas o coordenadas → spec futuro.
- Pantalla de administración del catálogo → descartado: el catálogo cambia por archivo fuente y seeder.
- Ubigeo RENIEC (códigos distintos usados en DNI) → descartado: SUNAT usa INEI.
- Uso del componente en proveedores y clientes → specs 021 y 025 (este spec solo deja la regla y la anotación).
- Formato del ubigeo en el XML UBL de comprobantes → spec 027 (consume el servicio de US-6).

## 7. Open Questions
- [x] Versión del archivo fuente INEI y origen. — **Resuelto:** códigos INEI de `jmcastagnetto/ubigeo-peru-aumentado` (1 892 distritos) con tildes completadas desde INEI 2016; detalle en `back-laravel/database/data/README.md`. Pendiente que Luis Acuña lo contraste con la publicación oficial INEI/SUNAT vigente.
- [x] ¿Se marca un código como no vigente (`is_active = false`) si INEI lo retira? — **Resuelto:** sí; el seeder lo desactiva, el componente lo oculta y `ValidUbigeo` lo acepta solo como valor actual del registro.
- [x] ¿Nombres en MAYÚSCULAS o en formato título? — **Resuelto:** se guardan en MAYÚSCULAS (INEI) y el frontend los muestra con `formatName()`.

## 8. Métricas de éxito
- `UbigeoSeederTest`, `UbigeoApiTest` y `UbigeoRuleTest` en verde; `CompanySettingsApiTest` y `BranchesApiTest` cubren el 422 por ubigeo inexistente.
- Pruebas Vitest del buscador cubren tildes, prefijos de código, código exacto y orden de resultados.
- 0 campos de texto libre de ubigeo y 0 columnas `department` / `province` / `district` en tablas de dominio tras cerrar el spec.
- Una sola petición a `/api/v1/ubigeo` por carga de la aplicación, con varios componentes en pantalla.
