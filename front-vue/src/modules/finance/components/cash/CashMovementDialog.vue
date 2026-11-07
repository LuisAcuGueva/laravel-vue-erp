<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { conceptsApi, paymentMethodsApi } from '@/modules/finance/services/financeSettingsApi'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  direction: {
    type: String,
    default: 'in', // 'in' o 'out'
    validator: val => ['in', 'out'].includes(val),
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const concepts = ref([])
const paymentMethods = ref([])
const isLoadingOptions = ref(false)

const defaultForm = {
  'concept_id': null,
  'payment_method_id': null,
  amount: '',
  reference: '',
  description: '',
  'counterparty_name': '',
}

const form = ref({ ...defaultForm })

const isIncome = computed(() => props.direction === 'in')
const dialogTitle = computed(() => isIncome.value ? 'Nuevo Ingreso de Efectivo' : 'Nuevo Egreso de Efectivo')
const dialogIcon = computed(() => isIncome.value ? 'tabler-arrow-down-left' : 'tabler-arrow-up-right')
const dialogColor = computed(() => isIncome.value ? 'success' : 'error')

const selectedPaymentMethod = computed(() => {
  return paymentMethods.value.find(m => m.id === form.value['payment_method_id']) || null
})

const requiresReference = computed(() => Boolean(selectedPaymentMethod.value?.requires_reference))

const loadOptions = async () => {
  isLoadingOptions.value = true
  try {
    const [conceptsRes, methodsRes] = await Promise.all([
      conceptsApi.options({ direction: props.direction }),
      paymentMethodsApi.options(),
    ])

    // Filtramos conceptos no de sistema y según dirección
    concepts.value = (conceptsRes?.data || []).filter(c => !c.is_system)

    // Movimientos manuales de caja requieren método liquidado en caja (efectivo)
    const validMethods = (methodsRes?.data || []).filter(m => m.settlement === 'cash_box')

    paymentMethods.value = validMethods

    // Preseleccionar el primer método de efectivo si no hay seleccionado
    if (validMethods.length > 0 && !form.value['payment_method_id']) {
      form.value['payment_method_id'] = validMethods[0].id
    }
  }
  catch {
    concepts.value = []
    paymentMethods.value = []
  }
  finally {
    isLoadingOptions.value = false
  }
}

watch(() => props.isDialogOpen, isOpen => {
  if (isOpen) {
    form.value = { ...defaultForm }
    clearErrors()
    loadOptions()
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
  emit('submit', {
    direction: props.direction,
    'concept_id': form.value.concept_id,
    'payment_method_id': form.value.payment_method_id,
    amount: Number(form.value.amount),
    reference: form.value.reference ? form.value.reference.trim() : null,
    description: form.value.description ? form.value.description.trim() : '',
    'counterparty_name': form.value.counterparty_name ? form.value.counterparty_name.trim() : null,
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
    max-width="540"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VIcon
            :icon="dialogIcon"
            :color="dialogColor"
            class="me-2"
          />
        </template>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>
        <VCardSubtitle>
          {{ isIncome ? 'Registro de entrada manual de dinero en efectivo' : 'Registro de salida manual de dinero en efectivo' }}
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

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Concepto -->
            <VCol cols="12">
              <VAutocomplete
                v-model="form.concept_id"
                :items="concepts"
                item-title="name"
                item-value="id"
                label="Concepto *"
                placeholder="Seleccione el concepto (ej. Cobro de flete)"
                :loading="isLoadingOptions"
                :error-messages="errors.concept_id"
                @update:model-value="clearFieldError('concept_id')"
              />
            </VCol>

            <!-- Monto -->
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                label="Monto (S/) *"
                placeholder="50.00"
                prefix="S/"
                :error-messages="errors.amount"
                @input="clearFieldError('amount')"
              />
            </VCol>

            <!-- Método de pago -->
            <VCol
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="form.payment_method_id"
                :items="paymentMethods"
                item-title="name"
                item-value="id"
                label="Método de Pago *"
                placeholder="Efectivo"
                :loading="isLoadingOptions"
                :error-messages="errors.payment_method_id"
                @update:model-value="clearFieldError('payment_method_id')"
              />
            </VCol>

            <!-- Referencia -->
            <VCol cols="12">
              <VTextField
                v-model="form.reference"
                :label="requiresReference ? 'Referencia / N° Operación *' : 'Referencia / N° Operación'"
                placeholder="Recibo N° 00123"
                maxlength="60"
                :error-messages="errors.reference"
                @input="clearFieldError('reference')"
              />
            </VCol>

            <!-- Contraparte -->
            <VCol cols="12">
              <VTextField
                v-model="form.counterparty_name"
                label="Entregado a / Recibido de"
                placeholder="Juan Pérez"
                maxlength="150"
                :error-messages="errors.counterparty_name"
                @input="clearFieldError('counterparty_name')"
              />
            </VCol>

            <!-- Descripción -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción / Motivo *"
                placeholder="Motivo del movimiento en caja"
                maxlength="255"
                rows="2"
                :error-messages="errors.description"
                @input="clearFieldError('description')"
              />
            </VCol>
          </VRow>
        </VForm>
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
          :color="dialogColor"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting"
          @click="handleSubmit"
        >
          {{ isIncome ? 'Registrar Ingreso' : 'Registrar Egreso' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
