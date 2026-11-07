---
description: Ejecuta una tarea T-N de .claude/specs/NNN-<feature>/todo.md respetando las reglas del repo y la arquitectura Monolito Modular Laravel 12 + Vue 3.
argument-hint: <NNN | NNN-feature | feature> <T-N>
---

# /spec-impl — Ejecutar una tarea

Vas a implementar **una tarea** de un spec. `$ARGUMENTS` = `<spec> <task-id>`, ej. `001 T-3` o `clients-crud T-3`.

## Pasos

1.  **Parsear argumentos:**
    *   `<spec>`: resolver con
        ```bash
        ls -d .claude/specs/<spec>/ .claude/specs/<spec>-*/ .claude/specs/[0-9][0-9][0-9]-<spec>/ 2>/dev/null | sort -u
        ```
        Exactamente un resultado → `<SPEC>`; si no, abortar mostrando `ls .claude/specs/`.
    *   `<task-id>`: formato `T-<N>`. Si falla el parseo, abortar mostrando el uso correcto.

2.  **Leer contexto** (estricto, en este orden):
    *   `<SPEC>/requirements.md` — el porqué.
    *   `<SPEC>/design.md` — el cómo.
    *   `<SPEC>/todo.md` — localizar `### T-<N> — …`: archivos clave, descripción, tests, criterios de done, dependencias.
    *   **Verificar dependencias:** las T-N previas de las que dependa deben estar marcadas como ✅. Si no, abortar y sugerir ejecutarlas primero.
    *   [.agents/rules/](../../.agents/rules/) — reglas duras del repo.
    *   **Cada archivo clave existente** — leerlo completo antes de editar para respetar convenciones existentes.

3.  **Implementar** respetando:
    *   **Backend Modules:** `app/Modules/<Modulo>/` con `Controllers/`, `Models/`, `Requests/`, `Resources/`, `Services/`, `Providers/`. Registro del ServiceProvider en `bootstrap/providers.php`.
    *   **Estilo y tipado:** `declare(strict_types=1);`, métodos tipados estrictos, validación en FormRequests con método `toAttributes()`.
    *   **Claves primarias:** UUIDs obligatorios en modelos y migraciones de dominio (`use HasUuids;`, `$keyType = 'string'`, `$incrementing = false`). Rutas con `->whereUuid('id')`.
    *   **Rutas:** registradas en `back-laravel/routes/api.php` bajo `/api/v1/<kebab-module>`.
    *   **Frontend:** Vue 3 Composition API (`<script setup>`), componentes Vuetify 3, tiendas Pinia, handlers MSW para mock de respuestas y textos en español.
    *   **Tests:** Feature Tests con PHPUnit en `back-laravel/tests/Feature/Modules/<Modulo>/`.
    *   **Sin alcance extra:** limitarse estrictamente a lo que pide la tarea T-N.

4.  **Verificar criterios de done** uno por uno:
    *   Backend:
        *   Tests: `make test` o bien `make test-filter FILTER=<NombreTest>` (ejecuta contra PostgreSQL en contenedor de test).
        *   Estilo / Formato: `cd back-laravel && vendor/bin/pint --test` (o ejecutar `./vendor/bin/pint` para aplicar correcciones de formato).
    *   Frontend:
        *   Linter: `cd front-vue && npm run lint`.
    *   Si algo falla, **corregirlo antes de marcar ✅** — nunca marcar ✅ con tests o linters en rojo.

5.  **Actualizar `<SPEC>/todo.md`:**
    *   Cambiar `🔧` → `✅` en la fila del mapa y en el campo "Estado:" del detalle.
    *   Al iniciar la primera tarea del spec, pasar el estado a `In Progress` en `.claude/specs/README.md` y en las cabeceras del spec.
    *   Si es la última tarea del spec, cambiar el estado a `Done` en `.claude/specs/README.md` y recordar mergear `<SPEC>/CLAUDE.md.delta.md` en `.agents/rules/`.

6.  **Cierre:**
    *   Resumir archivos tocados y mostrar el resultado de las verificaciones/tests.
    *   **Sugerir mensaje de commit** Conventional Commits en español: `feat(<modulo>): <resumen> (spec/NNN-feature#T-N)`.
    *   **Próximo paso:** la siguiente T-N sin dependencias pendientes (`/spec-impl <NNN> T-<N+1>`).
