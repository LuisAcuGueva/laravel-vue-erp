# 005 · api-resilience-and-exceptions — Requirements
> **Spec:** 005 · **Alcance:** back · **Módulo:** Transversal · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Done
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Auditoría arquitectónica para grado profesional - robustez de API y estándar de errores
>
> **Referencias del proyecto:**
> - [bootstrap/app.php](../../../back-laravel/bootstrap/app.php) — configuración de middleware y excepciones de Laravel 12.
> - [.agents/rules/database-primary-keys.md](../../../.agents/rules/database-primary-keys.md) — tipado estricto de UUIDs en controllers/services.
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API.
>
---
## 1. Contexto de Negocio
En una API REST profesional para software a medida consumida por una SPA (Vue 3), los clientes deben recibir respuestas estructuradas de forma predecible ante cualquier escenario: validación fallida, token expirado, permiso denegado, recurso inexistente o error no controlado del servidor.

Actualmente:
- `bootstrap/app.php` tiene el bloque `withExceptions()` vacío, lo que provoca respuestas heterogéneas de Laravel (HTML en ciertos 500s o JSON por defecto sin envoltorio uniforme).
- No existe un Correlation ID (`X-Request-Id`) para vincular los logs del servidor con los errores reportados por usuarios.
- No hay Rate Limiting configurado en `/auth/login` ni en `/v1/*`, permitiendo ataques de fuerza bruta o abusos de tráfico.
- En algunos controladores (ej. `ClientController::show(int|string $id)`) existen firmas con `int|string`, violando la regla dura de claves primarias UUID exclusivas.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Frontend SPA | Cliente Vue 3 (`$api` ofetch) | JWT Bearer Token |
| Usuario Final | Operador de la aplicación | Credenciales de login |
| Monitor / Logs | Sistema de observabilidad (Monolog) | Contexto de logging en PHP |

## 3. User Stories (formato EARS)
### US-1 — Respuestas y errores con formato unificado
**Como** desarrollador de frontend en Vue 3,
**quiero** que todas las respuestas de la API compartan una estructura estándar (`{ success, data, message, meta, errors }`),
**para** manejar la UI, alertas y errores de validación sin lógica dispersa ni excepciones de parseo.
**Cuando** ocurre un error HTTP (401, 403, 404, 422, 429, 500), **el sistema debe** responder en JSON con la estructura estándar y su código HTTP correspondiente.
**Criterios de aceptación:**
- [ ] Validación fallida (422) retorna `{ success: false, message: "Error de validación", errors: { "campo": ["error..."] } }`.
- [ ] Permiso denegado (403 Spatie) retorna `{ success: false, message: "No tienes permisos para realizar esta acción.", errors: null }`.
- [ ] No autenticado / JWT inválido (401) retorna `{ success: false, message: "No autenticado o token expirado.", errors: null }`.
- [ ] En entorno de producción (`APP_DEBUG=false`), un error 500 jamás expone la traza del stack ni datos de conexión a la base de datos.

### US-2 — Trazabilidad de peticiones vía Request ID
**Como** personal de soporte y desarrollo,
**quiero** que cada petición HTTP cuente con un identificador único correlativo (`X-Request-Id`),
**para** localizar en los logs de producción la causa exacta de un fallo reportado por un cliente.
**Cuando** ingresa una petición HTTP, **el sistema debe** generar o adoptar un UUID como `X-Request-Id`, inyectarlo en el logger y devolverlo en las cabeceras de respuesta.

### US-3 — Protección ante abusos y fuerza bruta (Rate Limiting)
**Como** administrador de seguridad,
**quiero** limitar la frecuencia de peticiones a endpoints sensibles,
**para** mitigar ataques de fuerza bruta en el login y sobrecargas en la API.
**Cuando** un cliente supera 5 intentos fallidos de login por minuto por IP/email, **el sistema debe** responder con código 429 (*Too Many Requests*).

### US-4 — Tipado estricto de identificadores UUID
**Como** arquitecto de software,
**quiero** que todos los métodos de controladores, servicios y DTOs tipen los IDs como `string`,
**para** cumplir con la regla de dominio del proyecto y evitar errores de conversión con Postgres.

## 4. Reglas de negocio e invariantes
- **Envoltorio estándar de respuesta (ApiResponse):**
  - Éxito: `{ "success": true, "data": ..., "message": "...", "meta": ... }`
  - Error: `{ "success": false, "message": "...", "errors": ... }`
- **Correlation ID:** Encabezado `X-Request-Id` presente en todas las respuestas HTTP salientes.
- **Cabeceras de Seguridad:** Inyección de `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.

## 5. Requisitos no funcionales
- Rendimiento del middleware de correlation y seguridad: latencia agregada menor a 1ms.
- 100% de cobertura en tests para los manejadores de excepciones personalizadas.

## 6. Out of Scope
- Integración directa con plataformas externas de observabilidad de pago (Datadog, New Relic) en esta etapa.

## 7. Open Questions
- [x] ¿Mantener compatibilidad con clientes existentes? *Sí, mediante el envelope estándar que incluye el nodo data original.*

## 8. Métricas de éxito
- Ninguna respuesta 500 devuelve HTML o trazas de código en producción.
- Rate limiting bloquea efectivamente el sexto intento fallido de autenticación en tests.
- Tipado `string $id` auditado en el 100% de los controladores.
