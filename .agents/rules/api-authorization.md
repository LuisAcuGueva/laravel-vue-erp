---
trigger: always_on
description: Toda ruta de negocio declara su permiso en el backend; la UI solo refleja.
---

## Autorización de endpoints

- Toda ruta bajo `/api/v1` que lea o modifique datos de negocio declara `->middleware('permission:<modulo>.<accion>')`. La única excepción son los endpoints `/options`, `/summary` y de perfil, que requieren solo autenticación y no exponen datos sensibles. Ocultar botones con `can()` en el frontend no sustituye esta regla.
- Cada módulo nuevo agrega a su test de API al menos un caso 403 por acción protegida.
- Los campos sensibles con permiso propio (p. ej. `credit_limit` con `clients.manage_credit`) se excluyen de `$fillable` y se actualizan solo con un endpoint dedicado.
