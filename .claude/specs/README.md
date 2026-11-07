# Specs — índice

> Specs del flujo SDD del proyecto (Monolito Modular Laravel 12 + Vue 3), numerados por **orden de creación** (`NNN-feature`). El número nunca se reutiliza.
> Comandos: `/spec-init` → `/spec-requirements` → `/spec-design` → `/spec-tasks` → `/spec-CLAUDE.md.delta` → `/spec-impl`.
> Reglas del repo: [.agents/rules/](../../.agents/rules/) · Makefile: [Makefile](../../Makefile) · Visión general: [README.md](../../README.md)

**Estados:** `Draft` → `Approved` → `In Progress` → `Done`

| # | Spec | Alcance | Módulo | Prioridad | Estado | Depende de specs | Origen |
|---|---|---|---|---|---|---|---|
| 001 | [permissions-module](./001-permissions-module/requirements.md) | fullstack | Permissions | alta | Done | — | Vistas de referencia `Template Multi Tenant/front-multi-tenancy` |
| 002 | [roles-module](./002-roles-module/requirements.md) | fullstack | Roles | alta | Done | 001 | Vistas de referencia `Template Multi Tenant/front-multi-tenancy` |
| 003 | [users-module](./003-users-module/requirements.md) | fullstack | Users | alta | Done | 001, 002 | Vistas de referencia `Template Multi Tenant/front-multi-tenancy` |
| 004 | [queue-and-scheduler-infra](./004-queue-and-scheduler-infra/requirements.md) | infra | Transversal | alta | Done | — | Auditoría arquitectónica - Workers y Scheduler |
| 005 | [api-resilience-and-exceptions](./005-api-resilience-and-exceptions/requirements.md) | back | Transversal | alta | Done | — | Auditoría arquitectónica - Envelope, Excepciones y Rate Limiting |
| 006 | [code-quality-and-ci](./006-code-quality-and-ci/requirements.md) | fullstack | Transversal | alta | Done | — | Auditoría arquitectónica - Larastan, Vitest y GitLab CI |
| 007 | [audit-trail](./007-audit-trail/requirements.md) | fullstack | Audit | media | Done | 001, 002, 003, 005 | Referencia `Template Multi Tenant/back-multi-tenancy/app/Core/Audit` |
| 008 | [frontend-crud-kit](./008-frontend-crud-kit/requirements.md) | front | Transversal | media | Done | 005 | Auditoría arquitectónica - ServerDataTable, useConfirm, errores 422 |
| 009 | [user-profile-and-sessions](./009-user-profile-and-sessions/requirements.md) | fullstack | Profile | media | Done | 001, 002, 003, 005 | Requerimiento transversal - Mi Cuenta y Revocación de Sesiones |
| 010 | [company-configuration](./010-company-configuration/requirements.md) | fullstack | Configuration | alta | Done | 005, 007, 008 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 011 | [branches-and-areas](./011-branches-and-areas/requirements.md) | fullstack | Maintenance | alta | Done | 003, 008 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 012 | [employees](./012-employees/requirements.md) | fullstack | Maintenance | alta | Done | 003, 011 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 013 | [inventory-masters](./013-inventory-masters/requirements.md) | fullstack | Inventory | alta | Done | 011 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 014 | [catalog-items](./014-catalog-items/requirements.md) | fullstack | Inventory | alta | Done | 010, 013 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 015 | [stock-and-kardex](./015-stock-and-kardex/requirements.md) | fullstack | Inventory | alta | Done | 010, 011, 014 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 016 | [warehouse-notes](./016-warehouse-notes/requirements.md) | fullstack | Inventory | alta | Done | 013, 014, 015 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 017 | [finance-masters](./017-finance-masters/requirements.md) | fullstack | Finance | media | Done | 010, 011 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 018 | [cash-registers-and-sessions](./018-cash-registers-and-sessions/requirements.md) | fullstack | Finance | media | Done | 002, 011, 014, 017 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 019 | [cash-transfers](./019-cash-transfers/requirements.md) | fullstack | Finance | media | Done | 018 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 020 | [bank-sessions-and-transfers](./020-bank-sessions-and-transfers/requirements.md) | fullstack | Finance | media | Done | 017, 018, 019 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 021 | [providers](./021-providers/requirements.md) | fullstack | Purchases | media | Done | 012, 014, 017 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 022 | [purchase-requests-and-orders](./022-purchase-requests-and-orders/requirements.md) | fullstack | Purchases | media | Done | 010, 011, 014, 015, 021 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 023 | [purchase-receipts](./023-purchase-receipts/requirements.md) | fullstack | Purchases | media | Done | 013, 015, 021, 022 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 024 | [accounts-payable-and-expenses](./024-accounts-payable-and-expenses/requirements.md) | fullstack | Purchases | media | Done | 017, 018, 020, 021, 022, 023 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 025 | [clients-crm-extension](./025-clients-crm-extension/requirements.md) | fullstack | Clients | media | Done | 003, 012 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 026 | [quotations](./026-quotations/requirements.md) | fullstack | Sales | media | Done | 004, 010, 012, 014, 015, 022, 025 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 027 | [sales-and-vouchers](./027-sales-and-vouchers/requirements.md) | fullstack | Sales | media | Done | 004, 011, 015, 018, 020, 024, 025, 026 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 028 | [accounts-receivable](./028-accounts-receivable/requirements.md) | fullstack | Sales | media | Done | 018, 020, 024, 025, 027 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 029 | [credit-notes](./029-credit-notes/requirements.md) | fullstack | Sales | media | Done | 015, 018, 024, 027, 028 | Migración Multinort — docs/plan_migracion_modulos_multinort.md |
| 030 | [ubigeo-peru](./030-ubigeo-peru/requirements.md) | fullstack | Ubigeo | alta | Done | 005, 008 | Requerimiento transversal — catálogo INEI y selector Departamento/Provincia/Distrito |
| 031 | [frontend-ui-standards](./031-frontend-ui-standards/requirements.md) | fullstack | Transversal | alta | Done | 008, 030 | Requerimiento transversal — estandarización UI (sidebar por secciones, tabla, alertas, inputs, breadcrumb en español) |
| 032 | [finance-accounting-setup](./032-finance-accounting-setup/requirements.md) | fullstack | Finance | alta | Done | 017, 018, 019, 020, 024, 028, 029 | Análisis de la configuración financiera — conceptos sin imputación al plan de cuentas |
| 033 | [session-tracking-and-audit-hardening](./033-session-tracking-and-audit-hardening/requirements.md) | fullstack | Auth/Profile + Audit | alta | In Progress | 005, 007, 009 | Revisión de `activity_log` en producción — sesiones disueltas en `refresh_tokens` y estandarización del activity log |
| 034 | [purchase-receipt-installment-plan](./034-purchase-receipt-installment-plan/requirements.md) | fullstack | Purchases | media | Done | 022, 023, 024 | — |
| 035 | [security-owasp-hardening](./035-security-owasp-hardening/requirements.md) | fullstack | Transversal (Auth + infra) | alta | Draft | 005, 007, 009, 033 | Auditoría de seguridad estática 2026-10-29 contra OWASP Top 10 / ASVS (15 hallazgos) |
