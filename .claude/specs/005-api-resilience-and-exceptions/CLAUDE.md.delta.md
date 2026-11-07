# Delta CLAUDE.md — 005 · api-resilience-and-exceptions

### Respuestas de API y Manejo de Errores
- Toda respuesta API debe cumplir con el formato envelope estándar `{ success, data, message, meta, errors }`.
- Las excepciones de validación, autorización y autenticación se manejan centralizadamente en `bootstrap/app.php`.
- Tipado estricto en controladores y servicios: **siempre usar `string $id` para parámetros UUID**.
- El middleware `EnsureRequestId` asigna `X-Request-Id` (UUID) en cada petición.
