<script setup>
import { useConfirm } from '@/composables/useConfirm'

const { isVisible, options, handleConfirm, handleCancel } = useConfirm()

const confirmBtn = ref(null)

// El botón de confirmar recibe el foco al abrir el diálogo.
watch(isVisible, async visible => {
  if (!visible)
    return

  await nextTick()
  confirmBtn.value?.$el?.focus()
})

// `persistent` evita el cierre por clic afuera; `Esc` equivale a «Cancelar».
const onKeydown = event => {
  if (event.key === 'Escape') {
    event.stopPropagation()
    handleCancel()
  }
}
</script>

<template>
  <VDialog
    :model-value="isVisible"
    max-width="480"
    persistent
    @keydown="onKeydown"
  >
    <VCard
      class="text-center px-6 py-6"
      role="alertdialog"
      :aria-label="options.title"
    >
      <VCardText class="d-flex flex-column align-center">
        <VAvatar
          size="64"
          :color="options.color || 'error'"
          variant="tonal"
          class="mb-4"
        >
          <VIcon
            :icon="options.icon || 'tabler-alert-triangle'"
            size="36"
          />
        </VAvatar>

        <h4 class="text-h5 font-weight-semibold mb-2">
          {{ options.title }}
        </h4>

        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ options.message }}
        </p>
      </VCardText>

      <VCardActions class="justify-center gap-4 mt-2">
        <VBtn
          v-if="!options.hideCancel"
          variant="tonal"
          color="secondary"
          @click="handleCancel"
        >
          {{ options.cancelText || 'Cancelar' }}
        </VBtn>

        <VBtn
          ref="confirmBtn"
          :color="options.color || 'error'"
          variant="elevated"
          @click="handleConfirm"
        >
          {{ options.confirmText || 'Confirmar' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
