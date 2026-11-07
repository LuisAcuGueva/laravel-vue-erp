# 006 · code-quality-and-ci — Requirements
> **Spec:** 006 · **Alcance:** fullstack · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** In Progress
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Auditoría arquitectónica para grado profesional - aseguramiento continuo de calidad
>
> **Referencias del proyecto:**
> - [back-laravel/composer.json](../../../back-laravel/composer.json) — dependencias de testing y linters backend.
> - [front-vue/package.json](../../../front-vue/package.json) — dependencias y scripts frontend.
> - [turbo.json](../../../turbo.json) — orquestador monorepo.
>
---
## 1. Contexto de Negocio
En software a medida para entornos empresariales, el código debe someterse a verificación estática y pruebas automatizadas en cada commit antes de desplegarse o fusionarse a las ramas principales (`main`/`develop`).

Actualmente:
- El backend sólo tiene Pint para estilo; no cuenta con **Larastan (PHPStan)** para análisis estático de tipos, lo que deja expuesto al sistema a errores de punteros nulos o incompatibilidad de métodos en tiempo de ejecución.
- El frontend no tiene configurado ningún framework de pruebas (ni **Vitest** ni `@vue/test-utils`), impidiendo verificar reactividad, stores de Pinia, ACL y lógica de `$api`.
- No existe integración continua en el repositorio: falta un pipeline para **GitLab CI** (`.gitlab-ci.yml`).

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Desarrollador | Autor de código / PR | Git push al repositorio |
| GitLab CI Runner | Agente de integración continua | Ejecución automática por disparador Git |

## 3. User Stories (formato EARS)
### US-1 — Análisis Estático Backend con Larastan
**Como** desarrollador de backend,
**quiero** ejecutar `composer phpstan` (Larastan nivel 8),
**para** detectar errores de tipado, parámetros inválidos y accesos nulos antes de probar en el navegador.
**Cuando** se ejecuta el análisis estático, **el sistema debe** validar todos los archivos en `app/` sin reportar errores de nivel 8.

### US-2 — Suite de Pruebas Unitarias Frontend con Vitest
**Como** desarrollador de frontend,
**quiero** ejecutar `pnpm --filter front-vue test:run`,
**para** asegurar que la lógica crítica de autenticación (`authStore`), cálculo de permisos (`useAcl`) y peticiones con reintento silencioso de tokens (`$api`) no sufra regresiones.
**Cuando** se ejecuta la suite de Vitest, **el sistema debe** evaluar los tests en entorno JSDOM y reportar 100% de éxito.

### US-3 — Pipeline Automatizado en GitLab CI
**Como** líder técnico,
**quiero** un archivo `.gitlab-ci.yml` configurado con etapas de validación,
**para** bloquear merges si algún linter, prueba o análisis estático falla.
**Cuando** se realiza un push a cualquier rama, **GitLab CI debe** ejecutar las etapas:
1. `lint` (Pint + ESLint + Stylelint)
2. `analyze` (Larastan)
3. `test` (PHPUnit con PostgreSQL en contenedor de servicio + Vitest)
4. `build` (Compilación de producción `front-vue:build` y verificación de `back-laravel`)

## 4. Reglas de negocio e invariantes
- Ningún commit en la rama principal puede tener advertencias o fallos de Larastan nivel 8.
- Turborepo debe orquestar los comandos unificados `pnpm run lint` y `pnpm run test` delegando en cada paquete del monorepo.
- Las pruebas de GitLab CI deben correr sobre un servicio real de PostgreSQL (versión 16).

## 5. Requisitos no funcionales
- Tiempo total del pipeline de CI inferior a 6 minutos mediante uso de caché de pnpm y composer.
- Compatibilidad completa de Vitest con el plugin de Vite y aliases de Vuetify (`@/`).

## 6. Out of Scope
- Pruebas E2E completas con navegadores reales en esta etapa (se integrarán en una fase posterior con Playwright).

## 7. Open Questions
- [x] ¿Plataforma de CI? *Decisión: GitLab CI (`.gitlab-ci.yml`).*

## 8. Métricas de éxito
- Comando `composer phpstan` operativo con 0 errores en nivel 8.
- Pruebas de `authStore`, `useAcl` y `$api` ejecutadas con Vitest en verde.
- Pipeline `.gitlab-ci.yml` completamente funcional.
