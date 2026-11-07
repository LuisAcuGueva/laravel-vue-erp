<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { providersApi } from '@/modules/purchases/services/providersApi'

/**
 * ProviderPicker.vue
 *
 * Selector remoto reutilizable de proveedores activos (specs 022–024).
 * Busca por documento o razón social con debounce de 250 ms sobre `/purchases/providers/options`.
 *
 * @prop {string|null} modelValue ID del proveedor seleccionado
 * @prop {object|null} initialItem Proveedor ya cargado (para mostrar el valor inicial sin buscar)
 * @event selected Emite el objeto proveedor completo (con `default_currency_id` y `credit_days`)
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
  label: {
    type: String,
    default: 'Proveedor',
  },
  placeholder: {
    type: String,
    default: 'Escribe razón social o RUC, p. ej. 20123456789',
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

const emit = defineEmits(['update:modelValue', 'selected'])

const search = ref('')
const items = ref(props.initialItem ? [props.initialItem] : [])
const isLoading = ref(false)
const selectedItem = ref(props.initialItem || null)

let debounceTimer = null

const formatTitle = provider => `${provider.document_number} · ${provider.business_name}`

const fetchOptions = async query => {
  isLoading.value = true
  try {
    const response = await providersApi.options({ search: (query || '').trim() || undefined })
    const list = response?.data || []

    items.value = selectedItem.value && !list.some(it => it.id === selectedItem.value.id)
      ? [selectedItem.value, ...list]
      : list
  }
  catch {
    items.value = selectedItem.value ? [selectedItem.value] : []
  }
  finally {
    isLoading.value = false
  }
}

watch(search, query => {
  // El texto que muestra el valor ya elegido no dispara una búsqueda nueva.
  if (selectedItem.value && query === formatTitle(selectedItem.value))
    return

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchOptions(query), 250)
})

watch(() => props.initialItem, val => {
  if (val) {
    selectedItem.value = val
    items.value = [val]
  }
})

const onSelect = id => {
  selectedItem.value = items.value.find(it => it.id === id) || null
  emit('update:modelValue', id ?? null)
  emit('selected', selectedItem.value)
}

onBeforeUnmount(() => clearTimeout(debounceTimer))
</script>

<template>
  <VAutocomplete
    v-model:search="search"
    :model-value="props.modelValue"
    :items="items"
    :item-title="formatTitle"
    item-value="id"
    :label="props.label"
    :placeholder="props.placeholder"
    :loading="isLoading"
    :disabled="props.disabled"
    :readonly="props.readonly"
    :clearable="props.clearable"
    :density="props.density"
    :error-messages="props.errorMessages"
    no-filter
    no-data-text="Sin resultados"
    @focus="fetchOptions(search)"
    @update:model-value="onSelect"
  />
</template>
