---
description: Genera o refina .claude/specs/NNN-<feature>/CLAUDE.md.delta.md con solo las reglas duras nuevas que el spec agrega a .agents/rules/.
argument-hint: <NNN | NNN-feature | feature>
---

# /spec-CLAUDE.md.delta — Llenar el delta de reglas

Vas a generar (o refinar) `CLAUDE.md.delta.md` de un spec: **solo** las reglas duras nuevas que el spec introduce y que deben vivir en `.agents/rules/` una vez implementado. En este repo las reglas duras no viven en un `CLAUDE.md` sino en archivos modulares de `.agents/rules/` con frontmatter (`trigger`, `description`), como `database-primary-keys.md`.

## Pasos

1.  **Resolver el spec** a partir de `$ARGUMENTS` (acepta `001`, `001-clients-crud` o `clients-crud`):
    ```bash
    ls -d .claude/specs/$ARGUMENTS/ .claude/specs/$ARGUMENTS-*/ .claude/specs/[0-9][0-9][0-9]-$ARGUMENTS/ 2>/dev/null | sort -u
    ```
    *   Exactamente un resultado → `<SPEC>`. Si no, abortar mostrando `ls .claude/specs/`.
    *   `<SPEC>/design.md` debe estar lleno. Si no, abortar y sugerir `/spec-design <NNN>`.

2.  **Leer contexto:**
    *   `<SPEC>/design.md` y `<SPEC>/todo.md` — fuente de las decisiones técnicas y reglas.
    *   [.agents/rules/](../../.agents/rules/) — reglas ya vigentes y sus archivos.
    *   `CLAUDE.md.delta.md` de specs anteriores aún no mergeados (estado ≠ `Done` en `.claude/specs/README.md`) — no duplicar.

3.  **Seleccionar qué entra.** Solo reglas que cumplan las tres condiciones:
    *   **Nuevas:** no están ya en `.agents/rules/` ni en otro delta pendiente.
    *   **Duraderas:** aplican a trabajo futuro, no solo a la implementación de este spec puntual (ej. "todo controlador que maneje subida de archivos debe usar el servicio `FileStorageService`", "todas las rutas con id deben incluir `->whereUuid('id')`").
    *   **Verificables:** concretas — un revisor o linter puede determinar si el código las cumple.

    No entran: descripciones del feature, tutoriales, contenido del README ni reglas genéricas de Laravel/Vue ya cubiertas por los skills instalados.

4.  **Escribir** con el formato del esqueleto de `/spec-init`: una sección `## (+ en ".agents/rules/<archivo>.md") <título>` por grupo de reglas, cada regla como bullet imperativo.
    *   Si el grupo merece un archivo nuevo, indicar `## (+ nuevo ".agents/rules/<archivo>.md", trigger: always_on) <título>` e incluir la `description` propuesta para el frontmatter.
    *   Si una regla **modifica** una existente, usar `## (~ en ".agents/rules/<archivo>.md") <título>` y citar la regla anterior.
    *   Si el spec no introduce reglas nuevas, dejar el archivo con una única línea: `Sin reglas nuevas — <motivo>.`

5.  **Cierre:** listar las reglas agregadas. **Próximo paso:** `/spec-impl <NNN> T-1`. El merge del delta en `.agents/rules/` se hace al completar la última tarea del spec.
