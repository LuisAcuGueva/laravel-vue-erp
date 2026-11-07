# 025 · clients-crm-extension — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/api-authorization.md", trigger: always_on) Autorización de endpoints
**description:** Toda ruta de negocio declara su permiso en el backend; la UI solo refleja.
- Toda ruta bajo `/api/v1` que lea o modifique datos de negocio declara `->middleware('permission:<modulo>.<accion>')`. La única excepción son los endpoints `/options`, `/summary` y de perfil, que requieren solo autenticación y no exponen datos sensibles. Ocultar botones con `can()` en el frontend no sustituye esta regla.
- Cada módulo nuevo agrega a su test de API al menos un caso 403 por acción protegida.
- Los campos sensibles con permiso propio (p. ej. `credit_limit` con `clients.manage_credit`) se excluyen de `$fillable` y se actualizan solo con un endpoint dedicado.

## (+ en ".agents/rules/identity-documents.md") Registros de sistema
- Los registros "de sistema" (`is_system = true`: cliente genérico, motivos, conceptos, métodos de pago) los crean los seeders, se referencian por código o con un helper estático (`Client::system()`) y el Service rechaza su edición o eliminación con 422.
