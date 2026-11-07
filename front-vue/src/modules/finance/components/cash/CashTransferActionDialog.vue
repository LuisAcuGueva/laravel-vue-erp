<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  transfer: {
    type: Object,
    default: null,
  },
  actionType: {
    type: String,
    default: 'reject', // 'reject' | 'cancel'
    validator: val => ['reject', 'cancel'].includes(val),
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const reason = ref('')

const isReject = computed(() => props.actionType === 'reject')
const title = computed(() => isReject.value ? 'Rechazar Transferencia' : 'Cancelar Transferencia')

const description = computed(() => isReject.value
  ? 'Indica el motivo por el cual se rechaza la transferencia de efectivo.'
  : 'Indica el motivo por el cual cancelas esta solicitud de transferencia.')

const buttonLabel = computed(() => isReject.value ? 'Rechazar transferencia' : 'Cancelar transferencia')
const buttonColor = computed(() => isReject.value ? 'error' : 'secondary')
const buttonIcon = computed(() => isReject.value ? 'tabler-x' : 'tabler-ban')

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
  if (!reason.value.trim() || reason.value.trim().length < 3) {
    setErrors({ reason: ['Debes indicar un motivo de al menos 3 caracteres.'] })
    
    return
  }
  emit('submit', reason.value.trim())
}

defineExpose({
  setErrors,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="500"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            :color="buttonColor"
            variant="tonal"
            rounded
          >
            <VIcon :icon="buttonIcon" />
          </VAvatar>
        </template>
        <VCardTitle>{{ title }}</VCardTitle>
        <VCardSubtitle>{{ description }}</VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <div
          v-if="props.transfer"
          class="mb-4 text-body-2"
        >
          <div class="d-flex justify-space-between mb-1">
            <span class="text-medium-emphasis">Transferencia:</span>
            <span class="font-weight-medium">{{ props.transfer.number }}</span>
          </div>
          <div class="d-flex justify-space-between mb-1">
            <span class="text-medium-emphasis">Monto:</span>
            <span class="font-weight-bold">S/ {{ props.transfer.amount }}</span>
          </div>
          <div class="d-flex justify-space-between">
            <span class="text-medium-emphasis">Motivo original:</span>
            <span>{{ props.transfer.reason }}</span>
          </div>
        </div>

        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-3"
        >
          {{ generalError }}
        </VAlert>

        <VTextarea
          v-model="reason"
          label="Motivo *"
          placeholder="Ej. Error en el monto o caja destino con turno cerrado"
          rows="3"
          auto-grow
          :error-messages="errors.reason"
          @update:model-value="clearFieldError('reason')"
        />
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="props.isSubmitting"
          @click="handleClose"
        >
          Volver
        </VBtn>
        <VBtn
          :color="buttonColor"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting"
          @click="handleSubmit"
        >
          <VIcon
            start
            :icon="buttonIcon"
          />
          {{ buttonLabel }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
