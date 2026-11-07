<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Anular documento',
  },
  documentNumber: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  description: {
    type: String,
    default: 'Esta acción anulará el documento y revertirá de manera definitiva cualquier movimiento de inventario asociado.',
  },
  reasonLabel: {
    type: String,
    default: 'Motivo de anulación *',
  },
  reasonPlaceholder: {
    type: String,
    default: 'Ej. Error en los ítems ingresados o duplicidad física del documento',
  },
  confirmLabel: {
    type: String,
    default: 'Confirmar anulación',
  },
  requiredMessage: {
    type: String,
    default: 'El motivo de anulación es obligatorio.',
  },
  color: {
    type: String,
    default: 'error',
  },

  // Cuando el motivo es opcional (ej. cerrar una orden) se confirma aunque esté vacío.
  reasonOptional: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const reason = ref('')
const error = ref('')

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    reason.value = ''
    error.value = ''
  }
})

const handleCancel = () => {
  emit('update:modelValue', false)
}

const handleConfirm = () => {
  const trimmed = reason.value.trim()
  if (!trimmed && props.reasonOptional) {
    emit('confirm', '')

    return
  }

  if (!trimmed) {
    error.value = props.requiredMessage

    return
  }

  if (trimmed.length < 5) {
    error.value = 'El motivo debe tener al menos 5 caracteres.'
    
    return
  }

  error.value = ''
  emit('confirm', trimmed)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="520"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            :color="color"
            variant="tonal"
            class="me-2"
          >
            <VIcon icon="tabler-alert-triangle" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          {{ title }}
        </VCardTitle>
        <VCardSubtitle v-if="documentNumber">
          Documento: <span class="font-weight-medium text-high-emphasis">{{ documentNumber }}</span>
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-2">
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ description }}
        </p>

        <VTextarea
          v-model="reason"
          :label="reasonLabel"
          :placeholder="reasonPlaceholder"
          rows="3"
          auto-grow
          :error-messages="error"
          @input="error = ''"
        />
      </VCardText>

      <VCardActions class="px-6 pb-6 pt-0 justify-end">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="handleCancel"
        >
          Cancelar
        </VBtn>
        <VBtn
          :color="color"
          variant="elevated"
          :loading="loading"
          @click="handleConfirm"
        >
          {{ confirmLabel }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
