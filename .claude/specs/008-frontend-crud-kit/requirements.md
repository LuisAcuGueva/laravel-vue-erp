# 008 · frontend-crud-kit — Requirements
> **Spec:** 008 · **Alcance:** front · **Módulo:** Transversal · **Prioridad:** media · **Depende de specs:** 005
> **Status:** Implemented
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Auditoría arquitectónica para grado profesional - aceleración de desarrollo y consistencia UI
>
> **Referencias del proyecto:**
> - [front-vue/src/pages/clients/index.vue](../../../front-vue/src/pages/clients/index.vue) — vista CRUD de referencia actual.
> - [front-vue/src/@core/components/](../../../front-vue/src/@core/components/) — componentes base del tema Vuetify.
>
---
## 1. Contexto de Negocio
En sistemas a medida, el 80% de las pantallas corresponden a operaciones de listado, filtrado, creación, edición y eliminación de entidades (CRUDs). 

Actualmente, cada módulo del frontend (`clients`, `users`, `roles`, `permissions`):
- Implementa manualmente el manejo de diálogos de confirmación (`isDeleteDialogOpen`, `clientToDelete`, `v-dialog`).
- Duplica la lógica de carga, ordenamiento y debounce de búsqueda de tablas.
- Carece de un mecanismo automático para mapear los errores de validación HTTP 422 devueltos por el backend hacia los `v-text-field` de Vuetify.
- No utiliza estados de esqueleto (*Skeleton Loaders*) durante la carga de datos del servidor, mostrando pantallas en blanco o parpadeos.

Este spec introduce un **CRUD Engine / Kit** transversal en Vue 3 que reduce en un 60% las líneas de código de cada vista y estandariza la experiencia de usuario.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Desarrollador Frontend | Implementador de pantallas de negocio | Entorno de desarrollo |
| Usuario Final | Operador de vistas CRUD | Sesión activa en frontend |

## 3. User Stories (formato EARS)
### US-1 — Mapeo automático de errores 422 a formularios
**Como** desarrollador frontend,
**quiero** vincular los errores de validación de `$api` directamente a un objeto reactivo de errores,
**para** que los inputs de Vuetify muestren sus mensajes de error (`:error-messages="errors.email"`) sin código repetitivo.
**Cuando** una petición POST o PUT falla con 422, **el sistema debe** poblar reactivamente el diccionario de errores y limpiar el error específico tan pronto como el usuario empiece a escribir en dicho campo.

### US-2 — Diálogo global de confirmación imperativo (`useConfirm`)
**Como** desarrollador frontend,
**quiero** invocar diálogos de confirmación con una promesa (`const confirmed = await confirm({ title: '¿Eliminar?', message: '...' })`),
**para** evitar declarar estados locales y componentes `v-dialog` redundantes en cada página.
**Cuando** el usuario hace clic en el botón de eliminar, **el sistema debe** renderizar un modal global y resolver la promesa en `true` si confirma o `false` si cancela.

### US-3 — Componente de Tabla Servidor (`ServerDataTable`)
**Como** desarrollador frontend,
**quiero** un componente `<ServerDataTable>` preconfigurado con búsqueda reactiva con debounce, selector de registros por página, ordenamiento por columna y paginación enlazada al envelope de Laravel,
**para** construir tablas de administración en menos de 30 líneas de plantilla.
**Cuando** la tabla está esperando la respuesta del servidor, **el sistema debe** renderizar automáticamente animaciones de esqueleto (*Skeleton Loaders*).

## 4. Reglas de negocio e invariantes
- **Consistencia visual:** Todo componente del CRUD Kit debe adherirse estrictamente a las variables y componentes de Vuetify 3 ya integrados en el tema (`@core`, `@layouts`).
- **Limpieza de estado:** Al cerrar un modal o drawer, los errores de validación deben resetearse automáticamente.

## 5. Requisitos no funcionales
- Rendimiento: El debounce de búsqueda en tabla debe estar fijado en 350ms para no saturar la API con peticiones intermedias.

## 6. Out of Scope
- Generadores de formularios visuales en tiempo de ejecución (no drag & drop builder, sino componentes Vue reutilizables).

## 7. Open Questions
- [x] ¿Reemplazar los CRUDs existentes o proveer los componentes para adopción? *Decisión: Proveer los componentes y refactorizar `clients` como caso de uso de referencia.*

## 8. Métricas de éxito
- Reducción de más de 100 líneas de código en `front-vue/src/pages/clients/index.vue`.
- Errores 422 resaltados en rojo bajo el input correspondiente automáticamente.
