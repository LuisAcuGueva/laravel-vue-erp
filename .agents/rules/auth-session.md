## Sesión multi-pestaña

- El refresh token y su CSRF son de la sesión del navegador, compartidos por todas las pestañas: se emiten juntos como cookies (`refresh_token` HttpOnly y `csrf_token` legible por JS) desde un solo lugar (`JwtService::makeRefreshTokenCookie()` + `makeCsrfCookie()`).
- El frontend lee el CSRF con `getCsrfCookie()` en cada petición; `sessionStorage` solo es respaldo. La sede activa y el access token siguen siendo por pestaña.
- El refresh se serializa entre pestañas con `withRefreshLock()` (`utils/authSync.js`).
- Un CSRF que no coincide responde 403 sin borrar las cookies de sesión; solo un token inválido, expirado o revocado las elimina.
- Una pestaña nueva arranca sin `sessionStorage`: `initAuth()` restaura la sesión con `restoreSessionFromCookie()` (refresh + `fetchUserProfile()`) cuando existe la cookie `csrf_token`; sin ella va a `/login`.
