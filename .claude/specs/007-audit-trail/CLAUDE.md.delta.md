# Delta CLAUDE.md — 007 · audit-trail

### Pistas de Auditoría (Audit Trail)
- Todos los modelos de dominio sensibles implementan el trait `LogsActivity` de Spatie.
- La tabla de auditoría utiliza clave primaria UUIDv7 y polimorfismo con UUID (`subject_id`, `causer_id`).
- Consulta protegida por permiso Spatie `audit.view` en `/api/v1/audit-logs`.
