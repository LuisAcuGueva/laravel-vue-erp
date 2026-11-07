# 006 · code-quality-and-ci — TODO
> **Spec:** 006 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Done
> **Última actualización:** 2026-10-14
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `back-laravel/composer.json`, `back-laravel/phpstan.neon` | — |
| T-2 | ✅ | back | M | Correcciones de tipado detectadas por PHPStan en `back-laravel/app/` | T-1 |
| T-3 | ✅ | front | M | `front-vue/package.json`, `front-vue/vite.config.js`, `src/tests/setup.js` | — |
| T-4 | ✅ | front | M | `front-vue/src/tests/unit/{useAcl,authStore,api}.spec.js` | T-3 |
| T-5 | ✅ | infra | S | `.gitlab-ci.yml`, `Makefile` | T-2, T-4 |

---
## Detalle de Tareas

### T-1 — Instalar y configurar Larastan en backend
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `back-laravel/composer.json`, `back-laravel/phpstan.neon`
**Descripción:** Agregar `larastan/larastan`, crear `phpstan.neon` en nivel 8 y registrar script `composer phpstan`.

### T-2 — Resolver observaciones de nivel 8 en backend
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:** `back-laravel/app/**`
**Descripción:** Ejecutar `vendor/bin/phpstan analyse` y corregir cualquier error de tipado o docstring hasta alcanzar 0 errores.

### T-3 — Instalar y configurar Vitest en frontend
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:** `front-vue/package.json`, `front-vue/vite.config.js`
**Descripción:** Añadir dependencias de Vitest, setup file con mocks necesarios y script `npm run test`.

### T-4 — Implementar tests unitarios críticos en frontend
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:** `front-vue/src/tests/unit/useAcl.spec.js`, `authStore.spec.js`, `api.spec.js`
**Descripción:** Cubrir ACL, manipulación de tokens y formato de peticiones HTTP en pruebas automatizadas.

### T-5 — Crear pipeline `.gitlab-ci.yml` y atajos en Makefile
**Estado:** ✅ · **Subj:** infra · **Complejidad:** S · **Depende de:** T-2, T-4
**Archivos clave:** `.gitlab-ci.yml`, `Makefile`
**Descripción:** Definir las etapas de GitLab CI y añadir `make phpstan` y `make test-front` al Makefile.
**Criterios de done:**
- [x] `make test` y `make test-front` corren satisfactoriamente en local.
