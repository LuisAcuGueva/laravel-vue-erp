# 010 · company-configuration — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/business-parameters.md", trigger: always_on) Parámetros de negocio y datos de empresa
**description:** Cómo leer y registrar parámetros de negocio configurables y los datos de la empresa.
- Leer toda regla de negocio configurable (topes de descuento, tolerancias, flags de inventario) con `SystemParameterService::get('<group>.<item>')` o sus atajos tipados; nunca como constante ni con `config()`.
- Registrar todo parámetro nuevo en `database/seeders/SystemParameterSeeder.php` con `updateOrCreate` por `(group_key, item_key)`, con `value_type` explícito y sin pisar `value` existente. La API no crea ni elimina parámetros.
- Nombrar las claves en `snake_case` con el módulo consumidor como `group_key` (`sales`, `inventory`, `finance`, `purchases`).
- Obtener los datos de la empresa (razón social, documento fiscal, logo, impuesto y moneda por defecto) con `CompanySettingService::current()`; nunca consultar `company_settings` directamente ni duplicar esos datos en otras tablas.
