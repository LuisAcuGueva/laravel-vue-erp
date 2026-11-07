# Delta CLAUDE.md — 008 · frontend-crud-kit

### Componentes y Patrones Frontend Reutilizables
- Para confirmaciones modales: usar `const { confirm } = useConfirm()` en lugar de diálogos locales repetitivos.
- Para validación en formularios Vuetify: usar `useApiFormErrors()` para poblar errores devueltos por la API (422).
- Para tablas de administración: usar `<ServerDataTable>` con paginación server-side y soporte de skeleton loaders.
