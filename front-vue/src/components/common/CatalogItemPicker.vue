<script setup>
/* eslint-disable camelcase, lines-around-comment */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { catalogApi } from '@/modules/inventory/services/catalogApi'

/**
 * CatalogItemPicker.vue
 *
 * Selector remoto reutilizable de ítems y presentaciones del catálogo.
 * Soporta búsqueda con debounce de 250 ms, filtrado por contexto (venta/compra/stock),
 * almacén opcional y selección inmediata con lectores de código de barras (Enter).
 *
 * @prop {string|null} modelValue ID de la presentación seleccionada (presentation_id)
 * @prop {object|null} initialItem Objeto de presentación inicial si ya fue cargado previamente
 * @prop {'sale'|'purchase'|'stock'} context Contexto de negocio que filtra los ítems
 * @prop {string|null} warehouseId ID del almacén para filtrar/consultar stock (spec 015)
 * @prop {string} label Etiqueta flotante del campo
 * @prop {string} placeholder Texto de sugerencia con ejemplo
 * @prop {boolean} required Indica si el campo es obligatorio
 * @prop {boolean} disabled Deshabilita la interacción con el campo
 * @prop {boolean} readonly Modo solo lectura
 * @prop {boolean} clearable Permite limpiar el valor seleccionado
 * @prop {string} density Densidad del input ('compact' | 'comfortable' | 'default')
 * @prop {string|string[]} errorMessages Mensajes de error de validación externos
 */

const props = defineProps({
  modelValue: {
    type: String,
    default: null,
  },
  initialItem: {
    type: Object,
    default: null,
  },
  context: {
    type: String,
    default: 'sale',
    validator: value => ['sale', 'purchase', 'stock'].includes(value),
  },
  warehouseId: {
    type: String,
    default: null,
  },
  label: {
    type: String,
    default: 'Artículo / Presentación',
  },
  placeholder: {
    type: String,
    default: 'Escribe nombre, código o escanea código de barras, p. ej. ITM-000001',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  density: {
    type: String,
    default: 'comfortable',
  },
  errorMessages: {
    type: [String, Array],
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'update:model-value', 'selected', 'change'])

const search = ref('')
const isLoading = ref(false)
const items = ref([])
const selectedItem = ref(props.initialItem || null)

let debounceTimer = null

// Formateador de presentación para la lista y el display
const formatTitle = item => {
  if (!item) return ''
  const presName = item.presentation_name ? ` (${item.presentation_name})` : ''
  
  return `${item.code} · ${item.name}${presName}`
}

// Si se pasa initialItem, agregarlo a items si no está
if (props.initialItem) {
  items.value = [props.initialItem]
}

// Ejecutar búsqueda remota
const fetchLookup = async query => {
  const q = (query || '').trim()
  if (!q) {
    items.value = selectedItem.value ? [selectedItem.value] : []
    
    return []
  }

  isLoading.value = true
  try {
    const params = {
      q,
      context: props.context,
      limit: 20,
    }

    if (props.warehouseId) {
      params.warehouse_id = props.warehouseId
    }

    const response = await catalogApi.lookup(params)
    const list = response.data || []

    // Si hay un ítem seleccionado actualmente, asegurar que esté en la lista
    if (selectedItem.value && !list.some(it => it.presentation_id === selectedItem.value.presentation_id)) {
      items.value = [selectedItem.value, ...list]
    } else {
      items.value = list
    }

    return list
  } catch {
    items.value = selectedItem.value ? [selectedItem.value] : []
    
    return []
  } finally {
    isLoading.value = false
  }
}

// Watch del texto buscado con debounce de 250 ms
watch(search, newQuery => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }

  const q = (newQuery || '').trim()

  // Si coincide exactamente con el título del ítem seleccionado, no relanzar búsqueda
  if (selectedItem.value && formatTitle(selectedItem.value) === newQuery) {
    return
  }

  if (!q) {
    items.value = selectedItem.value ? [selectedItem.value] : []
    
    return
  }

  debounceTimer = setTimeout(() => {
    fetchLookup(q)
  }, 250)
})

// Soporte para lector de código de barras (presiona Enter al final de la lectura)
const handleEnter = async event => {
  const q = (search.value || '').trim()
  if (!q) return

  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  const results = await fetchLookup(q)
  if (!results || results.length === 0) return

  // 1. Coincidencia exacta por código de barras
  const exactBarcode = results.find(it => it.barcode && it.barcode.toLowerCase() === q.toLowerCase())
  if (exactBarcode) {
    event?.preventDefault?.()
    select(exactBarcode.presentation_id)
    
    return
  }

  // 2. Coincidencia exacta por código SKU
  const exactCode = results.find(it => it.code && it.code.toLowerCase() === q.toLowerCase())
  if (exactCode) {
    event?.preventDefault?.()
    select(exactCode.presentation_id)
    
    return
  }

  // 3. Si solo hay un resultado, seleccionarlo directamente
  if (results.length === 1) {
    event?.preventDefault?.()
    select(results[0].presentation_id)
  }
}

// Selección de un ítem
const select = presentationIdOrItem => {
  const isObj = Boolean(presentationIdOrItem && typeof presentationIdOrItem === 'object')

  const value = isObj
    ? (presentationIdOrItem.presentation_id || presentationIdOrItem.id || null)
    : (presentationIdOrItem || null)

  if (value === (props.modelValue || null) && selectedItem.value?.presentation_id === value) {
    return
  }

  const found = isObj
    ? presentationIdOrItem
    : (items.value.find(it => it.presentation_id === value) || null)

  selectedItem.value = found

  emit('update:modelValue', value)
  emit('selected', found)
  emit('change', found)
}

// Watch externo de modelValue
watch(
  () => props.modelValue,
  newVal => {
    if (!newVal) {
      selectedItem.value = null
    } else if (selectedItem.value?.presentation_id !== newVal) {
      const found = items.value.find(it => it.presentation_id === newVal)
      if (found) {
        selectedItem.value = found
      }
    }
  },
)

// Watch de initialItem
watch(
  () => props.initialItem,
  item => {
    if (item) {
      selectedItem.value = item
      if (!items.value.some(it => it.presentation_id === item.presentation_id)) {
        items.value = [item, ...items.value]
      }
    }
  },
)

// Watch de warehouseId para refrescar búsqueda si cambia el almacén activo
watch(
  () => props.warehouseId,
  newWarehouseId => {
    if (newWarehouseId && search.value) {
      fetchLookup(search.value)
    }
  },
)

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
})

const rules = computed(() => (props.required ? [v => Boolean(v) || 'Seleccione un artículo'] : []))

const fieldErrors = computed(() => {
  return Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages].filter(Boolean)
})

defineExpose({
  selectedItem,
  fetchLookup,
  select,
})
</script>

<template>
  <div class="catalog-item-picker">
    <VAutocomplete
      v-model:search="search"
      :model-value="props.modelValue"
      :items="items"
      item-value="presentation_id"
      :item-title="formatTitle"
      :label="props.required ? `${props.label} *` : props.label"
      :placeholder="props.placeholder"
      :loading="isLoading"
      :disabled="props.disabled"
      :readonly="props.readonly"
      :clearable="props.clearable"
      :density="props.density"
      :rules="rules"
      :error-messages="fieldErrors"
      no-filter
      prepend-inner-icon="tabler-barcode"
      :no-data-text="isLoading ? 'Buscando artículos…' : 'Sin resultados (escriba nombre, código o escanee código de barras)'"
      :menu-props="{ contentClass: ['app-inner-list', 'app-autocomplete__content', 'v-autocomplete__content'] }"
      @update:model-value="select"
      @keydown.enter="handleEnter"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem
          v-bind="itemProps"
          :title="`${item.raw.name} — ${item.raw.presentation_name}`"
        >
          <template #subtitle>
            <div class="d-flex flex-wrap align-center gap-2 mt-1 text-caption">
              <span><strong>SKU:</strong> {{ item.raw.code }}</span>
              <span>•</span>
              <span><strong>Factor:</strong> {{ item.raw.factor }} {{ item.raw.unit?.symbol || '' }}</span>
              <span v-if="item.raw.barcode">•</span>
              <span v-if="item.raw.barcode"><strong>Barras:</strong> {{ item.raw.barcode }}</span>
              <span v-if="item.raw.stock !== undefined && item.raw.stock !== null">•</span>
              <span v-if="item.raw.stock !== undefined && item.raw.stock !== null">
                <strong>Stock:</strong> {{ typeof item.raw.stock === 'object' ? item.raw.stock.available : item.raw.stock }}
              </span>
            </div>
          </template>

          <template #append>
            <div
              v-if="item.raw.prices?.retail"
              class="text-right"
            >
              <div class="text-caption text-medium-emphasis">
                Precio
              </div>
              <div class="text-body-2 font-weight-bold text-primary">
                S/ {{ Number(item.raw.prices.retail).toFixed(2) }}
              </div>
            </div>
          </template>
        </VListItem>
      </template>

      <template #selection="{ item }">
        <span class="text-truncate">
          {{ formatTitle(item.raw) }}
        </span>
      </template>
    </VAutocomplete>
  </div>
</template>
