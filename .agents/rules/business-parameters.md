---
trigger: always_on
description: Cómo leer y registrar parámetros de negocio configurables y los datos de la empresa.
---

## Parámetros de negocio y datos de empresa

- Leer toda regla de negocio configurable (topes de descuento, tolerancias, flags de inventario) con `SystemParameterService::get('<group>.<item>')` o sus atajos tipados; nunca como constante ni con `config()`.
- Registrar todo parámetro nuevo en `database/seeders/SystemParameterSeeder.php` con `updateOrCreate` por `(group_key, item_key)`, con `value_type` explícito y sin pisar `value` existente. La API no crea ni elimina parámetros.
- Nombrar las claves en `snake_case` con el módulo consumidor como `group_key` (`sales`, `inventory`, `finance`, `purchases`).
- Obtener los datos de la empresa (razón social, documento fiscal, logo, impuesto y moneda por defecto) con `CompanySettingService::current()`; nunca consultar `company_settings` directamente ni duplicar esos datos en otras tablas.
