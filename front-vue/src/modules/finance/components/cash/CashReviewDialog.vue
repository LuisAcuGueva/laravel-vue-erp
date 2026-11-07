<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  session: {
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

const decision = ref('approved')
const notes = ref('')

watch(() => props.isDialogOpen, isOpen => {
  if (isOpen) {
    decision.value = 'approved'
    notes.value = ''
    clearErrors()
  }
}, { immediate: true })

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const difference = computed(() => Number(props.session?.difference || 0))

const handleClose = () => {
  if (props.isSubmitting)
    return
  emit('update:isDialogOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  if (decision.value === 'rejected' && !notes.value.trim()) {
    setErrors({ errors: { notes: ['Debe indicar el motivo del rechazo del arqueo.'] } })

    return
  }
  emit('submit', {
    decision: decision.value,
    notes: notes.value ? notes.value.trim() : null,
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="560"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VIcon
            icon="tabler-clipboard-check"
            color="primary"
            class="me-2"
          />
        </template>
        <VCardTitle>Revisión de Cierre de Caja</VCardTitle>
        <VCardSubtitle v-if="props.session">
          Sesión N° {{ props.session.number }} — {{ props.session.box?.name }}
        </VCardSubtitle>
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

        <!-- Datos del Arqueo a Revisar -->
        <VCard
          v-if="props.session"
          variant="tonal"
          color="warning"
          class="mb-4"
        >
          <VCardText class="pa-3 text-body-2">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-medium-emphasis">Cerrado por:</span>
              <span class="font-weight-medium">{{ props.session.closer?.name || '—' }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="text-medium-emphasis">Efectivo esperado:</span>
              <span class="font-weight-medium">{{ formatCurrency(props.session.expected_cash) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="text-medium-emphasis">Efectivo contado:</span>
              <span class="font-weight-medium">{{ formatCurrency(props.session.counted_cash) }}</span>
            </div>
            <div class="d-flex justify-space-between font-weight-bold pt-1 border-t">
              <span>Diferencia:</span>
              <span :class="difference >= 0 ? 'text-info' : 'text-error'">
                {{ difference >= 0 ? '+' : '' }}{{ formatCurrency(difference) }}
                ({{ difference >= 0 ? 'Sobrante' : 'Faltante' }})
              </span>
            </div>
            <div
              v-if="props.session.closing_notes"
              class="mt-2 pt-2 border-t text-caption"
            >
              <strong>Notas del cajero:</strong> {{ props.session.closing_notes }}
            </div>
          </VCardText>
        </VCard>

        <!-- Decisión de Supervisión -->
        <div class="mb-4">
          <label class="text-subtitle-2 font-weight-medium d-block mb-2">Decisión de Supervisión *</label>
          <VRadioGroup
            v-model="decision"
            inline
          >
            <VRadio
              label="Aprobar arqueo"
              value="approved"
              color="success"
            />
            <VRadio
              label="Rechazar arqueo"
              value="rejected"
              color="error"
            />
          </VRadioGroup>
        </div>

        <VTextarea
          v-model="notes"
          :label="decision === 'rejected' ? 'Observaciones de supervisión * (Obligatorio en rechazo)' : 'Observaciones de supervisión'"
          placeholder="Comentarios del supervisor respecto a la diferencia..."
          maxlength="1000"
          rows="3"
          :error-messages="errors.notes"
          @input="clearFieldError('notes')"
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
          :color="decision === 'approved' ? 'success' : 'error'"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting || (decision === 'rejected' && !notes.trim())"
          @click="handleSubmit"
        >
          {{ decision === 'approved' ? 'Aprobar Cierre' : 'Rechazar Cierre' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
