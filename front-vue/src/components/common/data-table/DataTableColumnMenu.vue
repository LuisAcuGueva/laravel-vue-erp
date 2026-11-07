<script setup>
import { useAuthStore } from '@/modules/auth/stores/authStore'

const props = defineProps({
  tableId: {
    type: String,
    required: true,
  },
  headers: {
    type: Array,
    default: () => [],
  },

  // Claves de las columnas ocultas.
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const authStore = useAuthStore()

const storageKey = computed(() => `dt-columns:${authStore.user?.id ?? 'anon'}:${props.tableId}`)

// `actions` y las columnas `alwaysVisible` no se pueden ocultar.
const toggleable = computed(() => props.headers.filter(h => h.key !== 'actions' && !h.alwaysVisible))

const defaultHidden = () => props.headers.filter(h => h.hidden).map(h => h.key)

const readStored = () => {
  try {
    const raw = window.localStorage.getItem(storageKey.value)
    const parsed = raw ? JSON.parse(raw) : null

    return Array.isArray(parsed) ? parsed : null
  }
  catch {
    return null
  }
}

const writeStored = value => {
  try {
    if (value === null)
      window.localStorage.removeItem(storageKey.value)
    else
      window.localStorage.setItem(storageKey.value, JSON.stringify(value))
  }
  catch {
    // localStorage no disponible: la elección vale solo para esta vista.
  }
}

watch(storageKey, () => {
  emit('update:modelValue', readStored() ?? defaultHidden())
}, { immediate: true })

const isVisible = key => !props.modelValue.includes(key)

const toggle = (key, visible) => {
  const hidden = visible
    ? props.modelValue.filter(k => k !== key)
    : [...new Set([...props.modelValue, key])]

  writeStored(hidden)
  emit('update:modelValue', hidden)
}

const reset = () => {
  writeStored(null)
  emit('update:modelValue', defaultHidden())
}
</script>

<template>
  <VMenu
    location="bottom end"
    :close-on-content-click="false"
  >
    <template #activator="{ props: menuProps }">
      <VTooltip text="Columnas">
        <template #activator="{ props: tooltipProps }">
          <VBtn
            v-bind="{ ...menuProps, ...tooltipProps }"
            icon
            variant="outlined"
            rounded="circle"
            size="38"
            color="secondary"
            aria-label="Columnas"
            data-testid="dt-columns"
          >
            <VIcon
              icon="tabler-columns-3"
              size="20"
            />
          </VBtn>
        </template>
      </VTooltip>
    </template>

    <VCard min-width="220">
      <VCardText class="pb-2">
        <p class="text-body-2 font-weight-medium mb-2">
          Columnas visibles
        </p>
        <VCheckbox
          v-for="header in toggleable"
          :key="header.key"
          :model-value="isVisible(header.key)"
          :label="header.title"
          density="compact"
          hide-details
          :data-testid="`dt-column-${header.key}`"
          @update:model-value="toggle(header.key, $event)"
        />
      </VCardText>
      <VDivider />
      <VCardActions class="px-4">
        <VBtn
          variant="text"
          size="small"
          prepend-icon="tabler-restore"
          data-testid="dt-columns-reset"
          @click="reset"
        >
          Restablecer
        </VBtn>
      </VCardActions>
    </VCard>
  </VMenu>
</template>
