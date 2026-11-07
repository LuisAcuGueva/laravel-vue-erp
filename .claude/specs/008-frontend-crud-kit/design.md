# 008 · frontend-crud-kit — Design
> **Spec:** 008 · **Alcance:** front · **Módulo:** Transversal · **Prioridad:** media · **Depende de specs:** 005
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara

---
## 1. Composable `useApiFormErrors`
Ubicación: `front-vue/src/composables/useApiFormErrors.js`

```javascript
import { ref } from 'vue'

export function useApiFormErrors() {
  const errors = ref({})
  const generalError = ref(null)

  const setErrors = (errorResponse) => {
    errors.value = {}
    generalError.value = null

    const data = errorResponse?.data || errorResponse?.response?._data
    if (data?.errors) {
      // Laravel 422: { field: ["error 1", "error 2"] }
      for (const [key, messages] of Object.entries(data.errors)) {
        errors.value[key] = Array.isArray(messages) ? messages[0] : messages
      }
    }
    if (data?.message) {
      generalError.value = data.message
    }
  }

  const clearErrors = () => {
    errors.value = {}
    generalError.value = null
  }

  const clearFieldError = (field) => {
    if (errors.value[field]) {
      delete errors.value[field]
    }
  }

  return {
    errors,
    generalError,
    setErrors,
    clearErrors,
    clearFieldError,
  }
}
```

## 2. Diálogo Global Imperativo `useConfirm`
Ubicación: `front-vue/src/composables/useConfirm.js` y componente montado en `App.vue`:

```javascript
import { ref } from 'vue'

const isVisible = ref(false)
const options = ref({
  title: '¿Confirmar acción?',
  message: '¿Estás seguro de que deseas continuar?',
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  color: 'error',
})
let resolvePromise = null

export function useConfirm() {
  const confirm = (customOptions = {}) => {
    options.value = { ...options.value, ...customOptions }
    isVisible.value = true
    return new Promise((resolve) => {
      resolvePromise = resolve
    })
  }

  const handleConfirm = () => {
    isVisible.value = false
    if (resolvePromise) resolvePromise(true)
  }

  const handleCancel = () => {
    isVisible.value = false
    if (resolvePromise) resolvePromise(false)
  }

  return {
    isVisible,
    options,
    confirm,
    handleConfirm,
    handleCancel,
  }
}
```

Uso en cualquier componente:
```javascript
const { confirm } = useConfirm()

const onDelete = async (item) => {
  if (await confirm({ title: 'Eliminar cliente', message: `¿Deseas eliminar a ${item.name}?` })) {
    await store.deleteClient(item.id)
  }
}
```

## 3. Componente `ServerDataTable.vue`
Ubicación: `front-vue/src/components/common/ServerDataTable.vue`
- Integra `v-data-table-server` de Vuetify 3.
- Recibe props:
  - `headers`: Array de columnas.
  - `items`: Colección de datos.
  - `totalItems`: Total reportado por la paginación (`meta.total`).
  - `loading`: Booleano de carga (activa `v-skeleton-loader`).
  - `search`: Texto de búsqueda vinculado con debounce de 350ms.
- Emite eventos: `@update:options="{ page, itemsPerPage, sortBy }"` para solicitar datos al servidor.
- Ranuras (*slots*) estándar para celdas personalizadas y acciones de fila (`#[item.actions]`).

## 4. Refactorización del Módulo de Referencia (`clients`)
- Reemplazar el modal de confirmación local de `pages/clients/index.vue` por `useConfirm`.
- Enlazar el drawer de formulario `ClientFormDrawer.vue` con `useApiFormErrors`.
