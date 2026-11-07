# 009 · user-profile-and-sessions — Requirements
> **Spec:** 009 · **Alcance:** fullstack · **Módulo:** Profile · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Done
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Requerimiento transversal de software a medida - gestión de cuenta propia y seguridad
>
> **Referencias del proyecto:**
> - [back-laravel/app/Modules/Auth/Models/RefreshToken.php](../../../back-laravel/app/Modules/Auth/Models/RefreshToken.php) — tabla de sesiones y refresh tokens.
> - [back-laravel/app/Models/User.php](../../../back-laravel/app/Models/User.php) — modelo de usuario con token_version.
>
---
## 1. Contexto de Negocio
En cualquier software a medida, los usuarios autenticados requieren una sección personal para:
1. Actualizar sus datos básicos de perfil (nombre, correo).
2. Cambiar su contraseña de acceso mediante confirmación de su contraseña actual.
3. Consultar los dispositivos y navegadores donde tienen sesiones abiertas y contar con la capacidad de **cerrar sesiones en otros dispositivos** (*Revoke Other Sessions*).

Actualmente, el sistema implementa rotación de refresh tokens (`refresh_tokens`), control de versión de tokens (`token_version`) y middleware JWT, pero **no expone endpoints ni vistas para que el usuario gestione su propio perfil o revoque sus sesiones activas**.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Usuario Autenticado | Cualquier usuario con sesión activa | Token JWT Bearer |

## 3. User Stories (formato EARS)
### US-1 — Actualización de información personal
**Como** usuario autenticado,
**quiero** editar mi nombre y correo electrónico desde mi perfil,
**para** mantener mis datos actualizados.
**Cuando** envío el formulario de perfil (`PUT /api/v1/profile`), **el sistema debe** validar que el correo no esté en uso por otro usuario y actualizar la información devolviendo el usuario actualizado.

### US-2 — Cambio seguro de contraseña
**Como** usuario autenticado,
**quiero** cambiar mi clave de acceso proporcionando mi clave actual y la nueva clave confirmada,
**para** proteger mi cuenta si sospecho de una vulneración.
**Cuando** envío el formulario de cambio de clave (`PUT /api/v1/profile/password`), **el sistema debe** verificar la contraseña actual mediante hash, almacenar la nueva clave hasheada e incrementar el `token_version` para invalidar tokens previos en otros dispositivos.

### US-3 — Gestión y revocación de sesiones activas
**Como** usuario autenticado,
**quiero** ver la lista de dispositivos donde he iniciado sesión (con IP, navegador y última actividad) y hacer clic en "Cerrar sesión en los demás dispositivos",
**para** evitar accesos no autorizados en terminales olvidadas.
**Cuando** el usuario solicita revocar otras sesiones (`DELETE /api/v1/profile/sessions/others`), **el sistema debe** marcar como revocados todos los registros en `refresh_tokens` excepto el del dispositivo actual.

## 4. Reglas de negocio e invariantes
- **Confirmación estricta de contraseña:** No se puede cambiar la contraseña sin suministrar la contraseña actual válida (`current_password`).
- **Aislamiento de cuenta:** Un usuario solo puede leer y alterar su propio perfil (`auth()->id()`), sin posibilidad de modificar a otros usuarios mediante estos endpoints.
- **Revocación:** Al cambiar de contraseña, se debe incrementar el `token_version` del usuario para invalidar todos los tokens JWT emitidos previamente.

## 5. Requisitos no funcionales
- Tiempo de respuesta de endpoints de perfil < 50ms.
- Ocultar hashes y campos confidenciales en respuestas JSON.

## 6. Out of Scope
- Configuración de 2FA por SMS o autenticador en este spec (se mantiene como extensión futura).

## 7. Open Questions
- [x] ¿Dónde se ubica la vista en frontend? *En `front-vue/src/pages/profile/index.vue` accesible desde el avatar del usuario en el AppBar.*

## 8. Métricas de éxito
- Cambio de contraseña funcional que invalida sesiones antiguas.
- Revocación de otras sesiones verificable en tests y en la base de datos.
