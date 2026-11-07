---
description: Genera o refina .claude/specs/NNN-<feature>/requirements.md (user stories EARS, módulo/área de negocio, permisos Spatie/CASL, criterios de aceptación, out-of-scope).
argument-hint: <NNN | NNN-feature | feature>
---

# /spec-requirements — Llenar requirements.md

Vas a generar (o refinar) `requirements.md` de un spec. Este documento describe el **qué/por qué**; el cómo va en `design.md`.

## Pasos

1.  **Resolver el spec** a partir de `$ARGUMENTS` (acepta `001`, `001-clients-crud` o `clients-crud`):
    ```bash
    ls -d .claude/specs/$ARGUMENTS/ .claude/specs/$ARGUMENTS-*/ .claude/specs/[0-9][0-9][0-9]-$ARGUMENTS/ 2>/dev/null | sort -u
    ```
    *   Exactamente un resultado → ese es `<SPEC>`. Cero o más de uno → abortar mostrando los specs disponibles (`ls .claude/specs/`).
    *   `<SPEC>/requirements.md` debe existir (creado por `/spec-init`). Si no, abortar y sugerir `/spec-init <feature>`.

2.  **Leer contexto:**
    *   `<SPEC>/requirements.md` — estado actual (refinar, no rehacer lo ya acordado).
    *   [README.md](../../README.md) — arquitectura de la aplicación, entornos (dev, test, prod) y automatización con Makefile.
    *   [.agents/rules/](../../.agents/rules/) — reglas duras vigentes (ej. claves primarias UUID).
    *   `back-laravel/routes/api.php` y `back-laravel/routes/web.php` — endpoints, middleware y prefijos existentes.
    *   Módulos existentes en `back-laravel/app/Modules/` y roles/permisos vigentes.
    *   `requirements.md` de los specs listados en "Depende de specs" — no contradecirlos; reutilizar sus actores y términos.
    *   **Referencia de estilo:** el `requirements.md` del spec con número inmediatamente anterior, si existe.

3.  **Recopilar inputs faltantes** con AskUserQuestion — solo lo que NO se pueda inferir del código, del README o de specs previos (actores, alcance MVP vs futuro, reglas de negocio ambiguas, métricas).

4.  **Escribir requirements.md** respetando:
    *   **Formato EARS** por US: `Cuando <trigger>, el sistema **debe** <comportamiento observable>`.
    *   **Numeración:** US-1, US-2, … nunca se renumeran; si una se descarta: `~~US-3~~ — descartada porque …`.
    *   **Módulo / Área** por US: nombre del módulo funcional y prefijo de ruta (ej. `Clients` en `/api/v1/clients`).
    *   **Permisos** por US: código Spatie/CASL `modulo.accion` (ej. `clients.view`, `clients.create`), `autenticado` o `público`.
    *   **Criterios de aceptación** observables: código HTTP, estructura del JSON de respuesta, estado persistido en PostgreSQL, comportamiento de UI. Ej: `Devuelve 422 con { "message": "...", "errors": { "email": [...] } }`. Para features con UI en Vue 3 / Vuetify: estados de carga (`loading`), tabla vacía, diálogo de confirmación y visibilidad según `useAcl().can()`.
    *   **Reglas de negocio e invariantes** explícitas (§4).
    *   **Requisitos no funcionales** (§5): validación estricta, rendimiento, gates de calidad (Pint, `make test`, ESLint).
    *   **Out of Scope** — al menos 3 ítems, apuntando al spec donde sí se resuelven (o "spec futuro").
    *   **Open Questions** con responsable/dueño.
    *   **Métricas de éxito** medibles.

5.  **NO hacer:**
    *   Detalles de implementación técnica (clases concretas, DDL de migraciones, componentes de Vue) — eso va en `design.md`.
    *   User stories vagas ("debe ser rápido", "debe escalar").
    *   Mezclar idiomas en un párrafo: español para narrativa y mensajes al usuario, inglés para identificadores técnicos.

6.  **Cierre:** resumir las US generadas/modificadas. **Próximo paso:** `/spec-design <NNN>`.
