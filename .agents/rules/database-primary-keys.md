---
trigger: always_on
description: Convención de claves primarias UUID para tablas de dominio en las migraciones de Laravel.
---

## Claves primarias

Este proyecto usa UUID como clave primaria en todas las tablas de dominio. Esto prevalece sobre los ejemplos con `$table->id()` de skills de terceros como `laravel-patterns` o `laravel-specialist`.

Reglas:
- Tablas de dominio: `$table->uuid('id')->primary();` y las FK como `$table->foreignUuid('x_id')` o `$table->uuid('x_id')`. Nunca `$table->id()` ni `foreignId()`.
- Modelos Eloquent: `use HasUuids;` (y `$keyType = 'string'`, `$incrementing = false` si no extienden un modelo que ya lo declare).
- Ids tipados como `string` en entidades, DTOs, repositorios, use cases y controllers; nunca `int` ni `(int)` casts.
- Rutas con `{id}` de recursos UUID: añadir `->whereUuid('id')` para responder 404 en lugar de un error de Postgres (`22P02`).
- Las tablas pivot de dominio (`user_branches`, etc.) también tienen `uuid('id')->primary()` y un modelo `extends Pivot` con `HasUuids`, enlazado con `->using()`.
- Los índices únicos de tablas con `SoftDeletes` se crean con `SoftDeleteUniqueIndex::make()`; las reglas `Rule::unique()` correspondientes agregan `->whereNull('deleted_at')`.
- Excepción: tablas internas del framework (`jobs`, `failed_jobs`, `job_batches`, `cache`, `sessions`, etc.) se mantienen como las genera Laravel.
