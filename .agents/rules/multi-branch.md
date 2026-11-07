---
trigger: always_on
description: Cómo se resuelve y aplica la sede activa en endpoints y datos que pertenecen a una sede.
---

## Contexto multi-sede

- Todo endpoint que lee o escribe datos que pertenecen a una sede (almacenes, cajas, series, ventas, compras) se registra con el middleware `branch`; el controller obtiene la sede con `app(ActiveBranch::class)` y la pasa al servicio. Nunca acepta `branch_id` del body o del query sin validarlo contra `UserBranchService::accessibleBranches()`.
- El frontend envía la sede activa en la cabecera `X-Branch-Id` (la inyecta `utils/api.js`); ningún componente la agrega manualmente. La sede activa es por pestaña (`sessionStorage`).
- Solo controllers y FormRequests leen `ActiveBranch`. Los servicios de dominio reciben la sede como parámetro; nunca llaman `app(ActiveBranch::class)` (deben funcionar en jobs, comandos y scheduler).
- Listados y altas usan la sede activa. `show`, `update` y acciones sobre un registro concreto lo autorizan con `BranchAccessGuard::assertAccessible($user, $model->branch_id)` (404 si la sede no es accesible), sin exigir que coincida con la sede activa.
- Toda descarga de archivos desde la API (PDF, CSV, adjuntos) usa `utils/download.js` (`$api` con blob); prohibidos `<a href>` y `window.open` directos a endpoints de la API.
- Las reglas de borrado de `Branch` viven en `BranchService::delete()`: todo spec que agregue una tabla con `branch_id` debe agregar ahí la verificación que impide eliminar una sede con registros dependientes.
