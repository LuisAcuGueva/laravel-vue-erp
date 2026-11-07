<script setup>
import { ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  movement: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const reason = ref('')

watch(() => props.isDialogOpen, isOpen => {
  if (isOpen) {
    reason.value = ''
    clearErrors()
  }
}, { immediate: true })

const handleClose = () => {
  if (props.isSubmitting)
    return
  emit('update:isDialogOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  if (!reason.value.trim()) {
    setErrors({ errors: { reason: ['Debe indicar el motivo de la anulación.'] } })

    return
  }
  emit('submit', reason.value.trim())
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="480"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VIcon
            icon="tabler-alert-triangle"
            color="error"
            class="me-2"
          />
        </template>
        <VCardTitle>Anular Movimiento de Caja</VCardTitle>
        <template #append>
          <VBtn
            icon
            variant="text"
            size="small"
            aria-label="Cerrar"
            :disabled="props.isSubmitting"
            @click="handleClose"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="pt-4">
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <p class="text-body-2 mb-2">
          ¿Está seguro de anular este movimiento manual? Esta acción registrará una reversión contable en el libro de caja.
        </p>

        <div
          v-if="props.movement"
          class="pa-3 bg-var-theme-background rounded mb-4 text-body-2"
        >
          <div><strong>Concepto:</strong> {{ props.movement.concept?.name }}</div>
          <div><strong>Monto:</strong> S/ {{ Number(props.movement.amount || 0).toFixed(2) }}</div>
          <div v-if="props.movement.description">
            <strong>Descripción:</strong> {{ props.movement.description }}
          </div>
        </div>

        <VTextarea
          v-model="reason"
          label="Motivo de la anulación *"
          placeholder="Ej. Error en digitación de monto o concepto duplicado"
          maxlength="255"
          rows="3"
          :error-messages="errors.reason"
          @input="clearFieldError('reason')"
        />
      </VCardText>

      <VCardActions class="pa-4 border-t justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="props.isSubmitting"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="error"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting || !reason.trim()"
          @click="handleSubmit"
        >
          Confirmar Anulación
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
