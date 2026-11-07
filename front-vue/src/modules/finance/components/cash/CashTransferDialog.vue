<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { boxesApi } from '@/modules/finance/services/cashApi'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  sourceSession: {
    type: Object,
    default: null,
  },
  sourceBox: {
    type: Object,
    default: null,
  },
  availableCash: {
    type: Number,
    default: 0,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const targetBoxes = ref([])
const isLoadingBoxes = ref(false)

const form = ref({
  'target_box_id': null,
  amount: '',
  reason: '',
})

const formatCurrency = val => {
  if (val === null || val === undefined || val === '')
    return '0.00'
  const num = Number(val)

  return Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const currentAvailable = computed(() => {
  return Number(props.availableCash || 0)
})

const selectedTargetBox = computed(() => {
  return targetBoxes.value.find(b => b.id === form.value['target_box_id']) || null
})

const targetBoxHasOpenSession = computed(() => {
  return Boolean(selectedTargetBox.value?.open_session)
})

const loadTargetBoxes = async () => {
  isLoadingBoxes.value = true
  try {
    const res = await boxesApi.list({ 'per_page': 100, 'is_active': '1' })
    const allBoxes = res?.data || []

    const currentBoxId = props.sourceBox?.id || props.sourceSession?.box_id

    targetBoxes.value = allBoxes.filter(b => b.id !== currentBoxId)
  }
  catch {
    targetBoxes.value = []
  }
  finally {
    isLoadingBoxes.value = false
  }
}

watch(
  () => props.isDialogOpen,
  isOpen => {
    if (isOpen) {
      clearErrors()
      form.value = {
        'target_box_id': null,
        amount: '',
        reason: '',
      }
      loadTargetBoxes()
    }
  },
)

const handleClose = () => {
  emit('update:isDialogOpen', false)
}

const handleSubmit = () => {
  clearErrors()

  const amt = Number.parseFloat(form.value.amount)
  if (Number.isNaN(amt) || amt <= 0) {
    setErrors({ amount: ['El monto debe ser mayor a 0.'] })

    return
  }

  if (amt > currentAvailable.value) {
    setErrors({ amount: [`El monto excede el efectivo disponible en caja (${formatCurrency(currentAvailable.value)}).`] })

    return
  }

  if (!form.value['target_box_id']) {
    setErrors({ 'target_box_id': ['Debes seleccionar una caja de destino.'] })

    return
  }

  if (!form.value.reason || form.value.reason.trim().length < 5) {
    setErrors({ reason: ['El motivo debe tener al menos 5 caracteres.'] })

    return
  }

  const payload = {
    'source_session_id': props.sourceSession?.id,
    'target_box_id': form.value['target_box_id'],
    amount: Number.parseFloat(form.value.amount),
    reason: form.value.reason.trim(),
  }

  emit('submit', payload, setErrors)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="560"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
          >
            <VIcon icon="tabler-arrows-left-right" />
          </VAvatar>
        </template>
        <VCardTitle>Solicitar Transferencia de Efectivo</VCardTitle>
        <VCardSubtitle>Envío de dinero desde esta caja hacia otra caja física</VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <!-- Resumen de caja de origen y saldo disponible -->
        <VAlert
          color="info"
          variant="tonal"
          density="comfortable"
          class="mb-4"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis">
                Caja de origen
              </div>
              <div class="font-weight-medium">
                {{ props.sourceBox?.name || 'Caja actual' }}
              </div>
            </div>
            <div class="text-end">
              <div class="text-caption text-medium-emphasis">
                Efectivo disponible
              </div>
              <div class="text-h6 font-weight-bold text-success">
                {{ formatCurrency(currentAvailable) }}
              </div>
            </div>
          </div>
        </VAlert>

        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Caja de destino -->
            <VCol cols="12">
              <VSelect
                v-model="form.target_box_id"
                :items="targetBoxes"
                item-title="name"
                item-value="id"
                label="Caja de destino *"
                placeholder="Selecciona la caja que recibirá el dinero"
                :error-messages="errors.target_box_id"
                :loading="isLoadingBoxes"
                @update:model-value="clearFieldError('target_box_id')"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem
                    v-bind="itemProps"
                    :subtitle="item.raw.branch ? item.raw.branch.name : ''"
                  >
                    <template #append>
                      <VChip
                        v-if="item.raw.open_session"
                        size="x-small"
                        color="success"
                        variant="tonal"
                      >
                        Sesión abierta
                      </VChip>
                      <VChip
                        v-else
                        size="x-small"
                        color="warning"
                        variant="tonal"
                      >
                        Sin sesión
                      </VChip>
                    </template>
                  </VListItem>
                </template>
              </VSelect>
            </VCol>

            <!-- Aviso si la caja de destino no tiene sesión abierta -->
            <VCol
              v-if="selectedTargetBox && !targetBoxHasOpenSession"
              cols="12"
              class="py-0"
            >
              <VAlert
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-2"
              >
                La caja seleccionada no tiene una sesión abierta en este momento. La transferencia se registrará como pendiente y solo podrá aprobarse cuando el cajero abra su turno.
              </VAlert>
            </VCol>

            <!-- Monto -->
            <VCol cols="12">
              <VTextField
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                :max="currentAvailable"
                label="Monto a transferir *"
                placeholder="0.00"
                prefix="S/"
                :error-messages="errors.amount"
                :hint="`Máximo disponible: ${formatCurrency(currentAvailable)}`"
                persistent-hint
                @update:model-value="clearFieldError('amount')"
              />
            </VCol>

            <!-- Motivo -->
            <VCol cols="12">
              <VTextarea
                v-model="form.reason"
                label="Motivo del traslado *"
                placeholder="Ej. Envío de sencillo para atención en caja 2"
                rows="3"
                auto-grow
                :error-messages="errors.reason"
                @update:model-value="clearFieldError('reason')"
              />
            </VCol>
          </VRow>
        </VForm>
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
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting || currentAvailable <= 0"
          @click="handleSubmit"
        >
          <VIcon
            start
            icon="tabler-send"
          />
          Solicitar transferencia
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
