---
trigger: "profile"
description: "Reglas para el módulo de gestión de perfil de usuario y sesiones concurrentes"
---

## Gestión de Perfil de Usuario y Sesiones

- Endpoints de perfil registrados bajo `/api/v1/profile` protegidos por `JwtAuthenticate` y rate limit `throttle:api`.
- Al cambiar contraseña en backend, siempre incrementar `$user->token_version` en base de datos para invalidar inmediatamente access tokens emitidos previamente.
- La interfaz de usuario se ubica en `front-vue/src/pages/profile/index.vue` organizada en pestañas de Cuenta, Seguridad y Dispositivos Activos.
- En revocación de sesiones, excluir la sesión activa identificada por el hash del `refresh_token` actual.
