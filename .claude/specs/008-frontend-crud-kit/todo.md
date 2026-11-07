# 008 · frontend-crud-kit — TODO
> **Spec:** 008 · **Alcance:** front · **Módulo:** Transversal · **Prioridad:** media · **Depende de specs:** 005
> **Status:** Complete
> **Última actualización:** 2026-10-14
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | front | S | `front-vue/src/composables/useApiFormErrors.js` | — |
| T-2 | ✅ | front | S | `front-vue/src/composables/useConfirm.js`, `src/components/common/GlobalConfirmDialog.vue`, `src/App.vue` | — |
| T-3 | ✅ | front | M | `front-vue/src/components/common/ServerDataTable.vue` | — |
| T-4 | ✅ | front | M | `front-vue/src/pages/clients/index.vue`, `src/modules/clients/components/ClientFormDrawer.vue` | T-1, T-2, T-3 |
| T-5 | ✅ | front | S | `front-vue/src/tests/unit/useApiFormErrors.spec.js`, `useConfirm.spec.js` | T-1, T-2 |

---
## Detalle de Tareas

### T-1 — Implementar composable `useApiFormErrors`
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `front-vue/src/composables/useApiFormErrors.js`
**Descripción:** Capturar respuesta 422 de `$api` y estructurar errores para inputs de Vuetify.

### T-2 — Implementar diálogo global imperativo `useConfirm`
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `front-vue/src/composables/useConfirm.js`, `GlobalConfirmDialog.vue`, `App.vue`
**Descripción:** Crear el composable con retorno de promesa y montar el componente visual en el layout raíz `App.vue`.

### T-3 — Construir componente `ServerDataTable.vue`
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:** `front-vue/src/components/common/ServerDataTable.vue`
**Descripción:** Envolver `v-data-table-server` con skeleton loaders, debounce de búsqueda y slots de acciones.

### T-4 — Refactorizar vista de Clientes con el nuevo Kit
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-1, T-2, T-3
**Archivos clave:** `front-vue/src/pages/clients/index.vue`, `ClientFormDrawer.vue`
**Descripción:** Eliminar código redundante y adoptar `useConfirm` y `useApiFormErrors`.

### T-5 — Tests unitarios para composables del kit
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-1, T-2
**Archivos clave:** `front-vue/src/tests/unit/useApiFormErrors.spec.js`, `useConfirm.spec.js`
**Descripción:** Verificar resolución de promesas en `confirm()` y parseo de errores 422.
