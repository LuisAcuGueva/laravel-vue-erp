# Delta CLAUDE.md — 009 · user-profile-and-sessions

### Gestión de Perfil de Usuario
- Endpoints bajo `/api/v1/profile` protegidos por `JwtAuthenticate`.
- Al cambiar contraseña, siempre incrementar `$user->token_version` para invalidar tokens concurrentes.
- Vista de usuario ubicada en `front-vue/src/pages/profile/index.vue`.
