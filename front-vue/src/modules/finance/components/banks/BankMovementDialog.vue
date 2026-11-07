<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { conceptsApi } from '@/modules/finance/services/financeSettingsApi'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  account: {
    type: Object,
    default: null,
  },
  initialDirection: {
    type: String,
    default: 'out', // 'in' o 'out'
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
const isLoadingConcepts = ref(false)

const form = ref({
  direction: 'out',
  'concept_id': null,
  amount: '',
  'operation_number': '',
  'movement_date': new Date().toISOString().substring(0, 10),
  'counterparty_name': '',
  description: '',
  voucher: null,
})

const isIncome = computed(() => form.value.direction === 'in')
const dialogTitle = computed(() => isIncome.value ? 'Nuevo Ingreso Bancario' : 'Nuevo Egreso Bancario')
const currencySymbol = computed(() => props.account?.currency?.symbol || 'S/')

const loadConcepts = async direction => {
  isLoadingConcepts.value = true
  try {
    const res = await conceptsApi.options({ direction })
    const list = res?.data || []

    // Filtrar conceptos que no sean de sistema
    concepts.value = list.filter(c => !c.is_system)
  }
  catch {
    concepts.value = []
  }
  finally {
    isLoadingConcepts.value = false
  }
}

watch(
  () => form.value.direction,
  newDir => {
    form.value['concept_id'] = null
    loadConcepts(newDir)
  },
)

watch(
  () => props.isDialogOpen,
  isOpen => {
    if (isOpen) {
      clearErrors()

      const targetDir = props.initialDirection || 'out'
      const dirChanged = form.value.direction !== targetDir

      form.value = {
        direction: targetDir,
        'concept_id': null,
        amount: '',
        'operation_number': '',
        'movement_date': new Date().toISOString().substring(0, 10),
        'counterparty_name': '',
        description: '',
        voucher: null,
      }

      if (!dirChanged) {
        loadConcepts(targetDir)
      }
    }
  },
  { immediate: true },
)

const handleClose = () => {
  clearErrors()
  emit('update:isDialogOpen', false)
}

const handleSubmit = () => {
  clearErrors()

  const formData = new FormData()

  formData.append('direction', form.value.direction)
  if (form.value['concept_id'])
    formData.append('concept_id', form.value['concept_id'])
  if (form.value.amount)
    formData.append('amount', form.value.amount)
  if (form.value['operation_number'])
    formData.append('operation_number', form.value['operation_number'])
  if (form.value['movement_date'])
    formData.append('movement_date', form.value['movement_date'])
  if (form.value['counterparty_name'])
    formData.append('counterparty_name', form.value['counterparty_name'])
  if (form.value.description)
    formData.append('description', form.value.description)

  if (form.value.voucher instanceof File) {
    formData.append('voucher', form.value.voucher)
  }
  else if (Array.isArray(form.value.voucher) && form.value.voucher.length > 0 && form.value.voucher[0] instanceof File) {
    formData.append('voucher', form.value.voucher[0])
  }

  emit('submit', formData)
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="isDialogOpen"
    max-width="600"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            :color="isIncome ? 'success' : 'error'"
            variant="tonal"
            class="me-2"
          >
            <VIcon
              :icon="isIncome ? 'tabler-arrow-down-left' : 'tabler-arrow-up-right'"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>
        <VCardSubtitle>
          {{ account?.alias || 'Cuenta bancaria' }} ({{ account?.account_number || '' }})
        </VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          closable
          class="mb-4"
          @click:close="generalError = null"
        >
          {{ generalError }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Tipo de movimiento -->
            <VCol cols="12">
              <VRadioGroup
                v-model="form.direction"
                inline
                class="mt-0"
              >
                <VRadio
                  label="Egreso (Salida)"
                  value="out"
                  color="error"
                />
                <VRadio
                  label="Ingreso (Abono)"
                  value="in"
                  color="success"
                />
              </VRadioGroup>
            </VCol>

            <!-- Concepto -->
            <VCol cols="12">
              <VAutocomplete
                v-model="form['concept_id']"
                :items="concepts"
                item-title="name"
                item-value="id"
                label="Concepto *"
                placeholder="ej. Mantenimiento de cuenta"
                :loading="isLoadingConcepts"
                :error-messages="errors.concept_id"
                clearable
                @update:model-value="clearFieldError('concept_id')"
              />
            </VCol>

            <!-- Monto -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                label="Monto *"
                placeholder="ej. 150.00"
                :prefix="currencySymbol"
                :error-messages="errors.amount"
                @update:model-value="clearFieldError('amount')"
              />
            </VCol>

            <!-- N° Operación bancaria -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['operation_number']"
                label="N° Operación bancaria"
                placeholder="ej. 0987654"
                :error-messages="errors.operation_number"
                @update:model-value="clearFieldError('operation_number')"
              />
            </VCol>

            <!-- Fecha de movimiento -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['movement_date']"
                type="date"
                label="Fecha de movimiento *"
                placeholder="ej. 2026-10-20"
                :error-messages="errors.movement_date"
                @update:model-value="clearFieldError('movement_date')"
              />
            </VCol>

            <!-- Contraparte -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['counterparty_name']"
                :label="isIncome ? 'Depositante' : 'Beneficiario'"
                placeholder="ej. Proveedor SAC, SUNAT"
                :error-messages="errors.counterparty_name"
                @update:model-value="clearFieldError('counterparty_name')"
              />
            </VCol>

            <!-- Descripción -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción *"
                placeholder="ej. Pago de mantenimiento mensual de cuenta corriente"
                rows="2"
                :error-messages="errors.description"
                @update:model-value="clearFieldError('description')"
              />
            </VCol>

            <!-- Voucher File -->
            <VCol cols="12">
              <VFileInput
                v-model="form.voucher"
                label="Comprobante / Voucher (opcional)"
                placeholder="Seleccione archivo PDF, PNG o JPG"
                accept=".pdf,image/png,image/jpeg"
                show-size
                clearable
                :error-messages="errors.voucher"
                @update:model-value="clearFieldError('voucher')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          :color="isIncome ? 'success' : 'primary'"
          variant="elevated"
          :loading="isSubmitting"
          @click="handleSubmit"
        >
          <VIcon
            start
            icon="tabler-check"
          />
          Guardar movimiento
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
