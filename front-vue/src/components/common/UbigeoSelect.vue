<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useUbigeoStore } from '@/modules/ubigeo/stores/ubigeoStore'
import { formatUbigeo, searchUbigeo } from '@/modules/ubigeo/utils/ubigeoSearch'

const props = defineProps({
  modelValue: {
    type: String,
    default: null,
  },
  label: {
    type: String,
    default: 'Ubigeo',
  },
  placeholder: {
    type: String,
    default: 'Escribe un distrito, p. ej. Miraflores',
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
  errorMessages: {
    type: [String, Array],
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const store = useUbigeoStore()

const search = ref('')

const isReady = computed(() => store.items.length > 0)

const toOption = ubigeo => ({
  code: ubigeo.code,
  department: ubigeo.department,
  province: ubigeo.province,
  district: ubigeo.district,
  label: formatUbigeo(ubigeo),
  title: `${formatUbigeo(ubigeo)} · ${ubigeo.code}`,
})

// Ubigeo elegido: departamento, provincia, distrito y código.
const selected = computed(() => {
  const ubigeo = store.find(props.modelValue)

  return ubigeo
    ? { code: ubigeo.code, department: ubigeo.department, province: ubigeo.province, district: ubigeo.district }
    : null
})

const isInvalid = computed(() => isReady.value && Boolean(props.modelValue) && !selected.value)

const options = computed(() => {
  const results = searchUbigeo(store.searchIndex, search.value).map(toOption)

  if (selected.value && !results.some(option => option.code === selected.value.code))
    results.unshift(toOption(selected.value))

  return results
})

const noDataText = computed(() => {
  const q = (search.value || '').trim()

  if (!isReady.value)
    return 'Cargando catálogo de ubigeo…'
  if (/^\d{6}$/.test(q))
    return `No se encontró el ubigeo ${q}`
  if (q.length < 2)
    return 'Escriba al menos 2 caracteres'

  return 'Sin resultados'
})

const fieldErrors = computed(() => {
  const external = Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages].filter(Boolean)

  return isInvalid.value ? ['Ubigeo no válido', ...external] : external
})

const rules = computed(() => (props.required
  ? [value => Boolean(value) || 'Seleccione el ubigeo']
  : []))

const select = code => {
  const value = code || null
  if (value === (props.modelValue || null))
    return

  emit('update:modelValue', value)

  const ubigeo = store.find(value)

  emit('change', ubigeo
    ? { code: ubigeo.code, department: ubigeo.department, province: ubigeo.province, district: ubigeo.district }
    : null)
}

// Código exacto de 6 dígitos: se selecciona sin elegirlo de la lista.
watch(search, value => {
  const q = (value || '').trim()
  if (/^\d{6}$/.test(q) && store.find(q)?.isActive)
    select(q)
})

const retry = () => {
  store.ensureLoaded().catch(() => {})
}

onMounted(retry)

defineExpose({ selected })
</script>

<template>
  <div class="ubigeo-select">
    <VAutocomplete
      v-model:search="search"
      :model-value="props.modelValue"
      :label="props.required ? `${props.label} *` : props.label"
      :placeholder="props.placeholder"
      prepend-inner-icon="tabler-map-pin"
      :items="options"
      item-title="title"
      item-value="code"
      no-filter
      clearable
      :no-data-text="noDataText"
      :loading="store.isLoading"
      :disabled="props.disabled || !isReady"
      :readonly="props.readonly"
      :rules="rules"
      :error-messages="fieldErrors"
      :menu-props="{ contentClass: ['app-inner-list', 'app-autocomplete__content', 'v-autocomplete__content'] }"
      @update:model-value="select"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem
          v-bind="itemProps"
          :title="item.raw.label"
          :subtitle="item.raw.code"
        />
      </template>
    </VAutocomplete>

    <div
      v-if="store.error && !isReady"
      class="d-flex align-center gap-2 mt-1 text-caption text-error"
      role="alert"
    >
      <span>{{ store.error }}</span>
      <VBtn
        size="x-small"
        variant="text"
        color="error"
        :loading="store.isLoading"
        @click="retry"
      >
        Reintentar
      </VBtn>
    </div>
  </div>
</template>
