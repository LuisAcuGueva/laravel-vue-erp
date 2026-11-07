---
description: Genera o refina .claude/specs/NNN-<feature>/todo.md (tareas T-N ejecutables, autocontenidas, con tests y dependencias explícitas para Laravel 12 + Vue 3).
argument-hint: <NNN | NNN-feature | feature>
---

# /spec-tasks — Llenar todo.md

Vas a generar (o refinar) `todo.md` de un spec. Cada tarea T-N debe ser **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto extra.

## Pasos

1.  **Resolver el spec** a partir de `$ARGUMENTS` (acepta `001`, `001-clients-crud` o `clients-crud`):
    ```bash
    ls -d .claude/specs/$ARGUMENTS/ .claude/specs/$ARGUMENTS-*/ .claude/specs/[0-9][0-9][0-9]-$ARGUMENTS/ 2>/dev/null | sort -u
    ```
    *   Exactamente un resultado → `<SPEC>`. Si no, abortar mostrando `ls .claude/specs/`.
    *   `<SPEC>/requirements.md` y `<SPEC>/design.md` deben estar llenos. Si no, abortar.

2.  **Leer contexto:**
    *   `<SPEC>/requirements.md`, `<SPEC>/design.md`.
    *   `<SPEC>/todo.md` — estado actual (**preservar** tareas ✅ y sus IDs).
    *   [.agents/rules/](../../.agents/rules/) — reglas generales del proyecto.
    *   **Referencia de estilo:** el `todo.md` del spec con número inmediatamente anterior, si existe.

3.  **Producir el documento:**

    ### Mapa de Tareas (tabla)
    Columnas: `ID | Estado | Subj | Compl | Archivos clave | Depende de`. Estados: 🔧 pendiente · ✅ hecho · ⚪ opcional. Subj: `back` | `front` | `infra` | `docs`. Complejidad S (≤ 1 h), M (≤ ½ día), L (> ½ día).

    ### Detalle por Tarea
    Una sub-sección `### T-N — título` por fila, con:
    *   **Estado, Subj, Complejidad, Depende de** (tareas de este spec).
    *   **Archivos clave** — rutas exactas en el proyecto (`back-laravel/app/Modules/...`, `front-vue/src/...`, `back-laravel/tests/...`).
    *   **Descripción** — pasos puntuales, en orden y sin ambigüedad.
    *   **Tests** — archivos de prueba y qué verifica cada uno (indicando el criterio de aceptación que cubre).
    *   **Criterios de done** — verificaciones observables; siempre incluye los gates del subproyecto tocado:
        *   back: `make test` o `make test-filter FILTER=<NombreTest>`, y `cd back-laravel && vendor/bin/pint --test` (o `./vendor/bin/pint`).
        *   front: `cd front-vue && npm run lint`.
        *   infra: comando Make o Docker respectivo (`make dev-up`, `docker compose config`).
    *   **Riesgos / notas** — edge cases o detalles no obvios.

4.  **Granularidad:**
    *   Cada tarea ≤ M por default. Si algo es L, partirlo en 2 o 3 M.
    *   Una tarea = un cambio coherente que se revisa y commitea como unidad.
    *   **Orden típico:**
        1.  Migración con PK UUID en `back-laravel/database/migrations/`.
        2.  Modelo Eloquent (`HasUuids`) + Factory en `back-laravel/database/factories/`.
        3.  Clase de servicio en `back-laravel/app/Modules/<Modulo>/Services/<Singular>Service.php`.
        4.  FormRequests y Resource en `back-laravel/app/Modules/<Modulo>/Requests/` y `Resources/`.
        5.  Controller, rutas en `back-laravel/routes/api.php` y registro de ServiceProvider en `bootstrap/providers.php`. *(Tip: para módulos estándar se puede usar el generador `php artisan make:module <Name> --all --uuid` y refinar).*
        6.  Feature Tests en `back-laravel/tests/Feature/Modules/<Modulo>/` verificados con `make test-filter`.
        7.  Frontend: mocks MSW + store Pinia + composable API.
        8.  Frontend: vista/página en `front-vue/src/pages/<kebab>/` con componentes Vuetify 3 y estados reactivos.
        9.  Documentación y delta de reglas si aplica.

5.  **Reglas:**
    *   Cada tarea nombra **al menos un archivo** y **al menos un test** o comando de verificación.
    *   Dependencias explícitas entre tareas.
    *   Si una tarea modifica un archivo existente: "leer primero `<archivo>` y respetar sus convenciones".
    *   No introducir reglas nuevas aquí — deben venir del design o de `.agents/rules/`.

6.  **Cierre:** resumir número de tareas y complejidad por subproyecto. **Próximo paso:** `/spec-CLAUDE.md.delta <NNN>` y luego `/spec-impl <NNN> T-1`.
