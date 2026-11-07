---
trigger: always_on
description: Cómo se guarda, valida, captura y muestra cualquier ubicación de Perú (ubigeo INEI, departamento, provincia, distrito).
---

## Ubigeo (departamento, provincia, distrito)

**Persistencia**
- Guardar la ubicación solo como código INEI de 6 dígitos en una columna `char(6)` llamada `ubigeo` (o `<prefijo>_ubigeo` si la tabla tiene varias). Nunca crear columnas `department`, `province`, `district` ni equivalentes con nombres en tablas de dominio.
- No declarar FK a `ubigeos`; la integridad la da la regla de validación.
- El catálogo `ubigeos` es de solo lectura para la aplicación: cambia solo por `back-laravel/database/data/ubigeo_inei.csv` + `UbigeoSeeder` (idempotente, `upsert` por `code`). Nunca borrar filas: un código retirado se marca `is_active = false`. Nunca agregar endpoints de escritura del catálogo.
- Usar el código INEI (el que exige SUNAT), nunca el de RENIEC.

**Validación (backend)**
- Validar todo campo de ubigeo con `new App\Modules\Ubigeo\Rules\ValidUbigeo($valorActual)` junto a `nullable` o `required`; prohibido validarlo solo con `digits:6`, `size:6` o `regex`.
- En `update`, pasar a `ValidUbigeo` el ubigeo guardado del registro para que un código inactivo existente siga siendo aceptado.
- En tests que guardan ubigeo, sembrar el catálogo con el trait `Tests\Concerns\SeedsUbigeoFixture` (`$this->seedUbigeoFixture()` en `setUp`).

**Lectura (backend)**
- Obtener nombres de departamento, provincia y distrito solo con `UbigeoService` (`find()`, `describe()`); nunca consultar `ubigeos` directamente desde otros módulos ni hacer un join por fila en listados, PDFs o exportaciones.

**Frontend**
- Todo formulario que capture ubicación usa `<UbigeoSelect v-model="form.ubigeo" :error-messages="errors.ubigeo" />` (`front-vue/src/components/common/UbigeoSelect.vue`, un `VAutocomplete` con label flotante), un solo campo con búsqueda; el detalle (departamento, provincia, distrito) se obtiene con su evento `change`. Prohibido un campo de texto libre para el código de ubigeo o selects propios de departamento/provincia/distrito.
- El valor enviado a la API es solo el código (`string` de 6 dígitos o `null`); nunca enviar nombres.
- Para mostrar una ubicación guardada usar `useUbigeo().describe(code)` (`front-vue/src/modules/ubigeo/composables/useUbigeo.js`). Ningún componente llama directamente a `/v1/ubigeo`: el catálogo se obtiene solo con `useUbigeoStore().ensureLoaded()`.
