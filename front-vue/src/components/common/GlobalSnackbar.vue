<script setup>
import { useNotify } from '@/composables/useNotify'

// Pausa entre toasts para que el anterior termine su transición de salida.
const GAP_MS = 250

const { current, dismiss } = useNotify()

const isVisible = ref(false)
const shown = ref(null)

// Muestra un toast a la vez; al cerrarse, desencola y muestra el siguiente.
const showCurrent = () => {
  if (isVisible.value || !current.value)
    return

  shown.value = current.value
  isVisible.value = true
}

watch(current, showCurrent, { immediate: true })

const onVisibilityChange = visible => {
  if (visible || !isVisible.value)
    return

  isVisible.value = false
  setTimeout(() => {
    dismiss()
    showCurrent()
  }, GAP_MS)
}
</script>

<template>
  <VSnackbar
    :model-value="isVisible"
    :color="shown?.color"
    location="top end"
    :timeout="3000"
    variant="flat"
    @update:model-value="onVisibilityChange"
  >
    <div
      v-if="shown"
      class="d-flex align-center gap-3"
      role="status"
    >
      <VIcon
        :icon="shown.icon"
        size="22"
      />
      <span>{{ shown.message }}</span>
    </div>

    <template #actions>
      <IconBtn
        size="small"
        aria-label="Cerrar notificación"
        @click="onVisibilityChange(false)"
      >
        <VIcon
          icon="tabler-x"
          size="18"
        />
      </IconBtn>
    </template>
  </VSnackbar>
</template>
