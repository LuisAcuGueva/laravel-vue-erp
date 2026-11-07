<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { banksApi } from '@/modules/finance/services/banksApi'
import { cashSessionsApi } from '@/modules/finance/services/cashApi'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  initialType: {
    type: String,
    default: 'deposit', // 'deposit', 'withdrawal', 'transfer'
    validator: val => ['deposit', 'withdrawal', 'transfer'].includes(val),
  },
  preselectedBankAccountId: {
    type: String,
    default: null,
  },
  preselectedCashSessionId: {
    type: String,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const bankAccounts = ref([])
const cashSessions = ref([])
const isLoadingOptions = ref(false)

const form = ref({
  type: 'deposit',
  'bank_account_id': null,
  'target_bank_account_id': null,
  'cash_session_id': null,
  amount: '',
  'exchange_rate': '',
  'operation_number': '',
  'operation_date': new Date().toISOString().substring(0, 10),
  voucher: null,
  notes: '',
})

const isDeposit = computed(() => form.value.type === 'deposit')
const isWithdrawal = computed(() => form.value.type === 'withdrawal')
const isTransfer = computed(() => form.value.type === 'transfer')

const dialogTitle = computed(() => {
  if (isDeposit.value)
    return 'Depositar en Banco (Caja → Banco)'
  if (isWithdrawal.value)
    return 'Retirar a Caja (Banco → Caja)'

  return 'Transferencia entre Cuentas Bancarias'
})

const dialogIcon = computed(() => {
  if (isDeposit.value)
    return 'tabler-arrow-down-left'
  if (isWithdrawal.value)
    return 'tabler-arrow-up-right'

  return 'tabler-arrows-left-right'
})

const dialogColor = computed(() => {
  if (isDeposit.value)
    return 'success'
  if (isWithdrawal.value)
    return 'info'

  return 'primary'
})

const selectedSourceAccount = computed(() => {
  return bankAccounts.value.find(a => a.id === form.value['bank_account_id']) || null
})

const selectedTargetAccount = computed(() => {
  return bankAccounts.value.find(a => a.id === form.value['target_bank_account_id']) || null
})

const availableTargetAccounts = computed(() => {
  return bankAccounts.value.filter(a => a.id !== form.value['bank_account_id'])
})

const isDifferentCurrencyTransfer = computed(() => {
  if (!isTransfer.value || !selectedSourceAccount.value || !selectedTargetAccount.value)
    return false

  return selectedSourceAccount.value.currency_id !== selectedTargetAccount.value.currency_id
})

const targetAmount = computed(() => {
  const amt = Number(form.value.amount)
  if (!amt || Number.isNaN(amt))
    return 0

  if (!isDifferentCurrencyTransfer.value)
    return amt

  const rate = Number(form.value['exchange_rate'])
  if (!rate || Number.isNaN(rate))
    return 0

  return Number((amt * rate).toFixed(2))
})

const loadOptions = async () => {
  isLoadingOptions.value = true
  try {
    const [accountsRes, sessionsRes] = await Promise.all([
      banksApi.listAccounts({ 'is_active': true }),
      cashSessionsApi.list({ status: 'open' }),
    ])

    bankAccounts.value = accountsRes?.data || []
    cashSessions.value = sessionsRes?.data || []
  }
  catch {
    bankAccounts.value = []
    cashSessions.value = []
  }
  finally {
    isLoadingOptions.value = false
  }
}

watch(
  () => props.isDialogOpen,
  async isOpen => {
    if (isOpen) {
      clearErrors()
      form.value = {
        type: props.initialType || 'deposit',
        'bank_account_id': props.preselectedBankAccountId || null,
        'target_bank_account_id': null,
        'cash_session_id': props.preselectedCashSessionId || null,
        amount: '',
        'exchange_rate': '',
        'operation_number': '',
        'operation_date': new Date().toISOString().substring(0, 10),
        voucher: null,
        notes: '',
      }

      await loadOptions()

      // Si no hay cuenta preseleccionada y hay cuentas disponibles, preseleccionar la primera
      if (!form.value['bank_account_id'] && bankAccounts.value.length > 0) {
        form.value['bank_account_id'] = bankAccounts.value[0].id
      }

      // Si no hay sesión de caja preseleccionada y hay sesiones abiertas, preseleccionar la primera
      if (!form.value['cash_session_id'] && cashSessions.value.length > 0) {
        form.value['cash_session_id'] = cashSessions.value[0].id
      }
    }
  },
)

const handleClose = () => {
  clearErrors()
  emit('update:isDialogOpen', false)
}

const handleSubmit = () => {
  clearErrors()

  const formData = new FormData()

  formData.append('type', form.value.type)
  if (form.value['bank_account_id'])
    formData.append('bank_account_id', form.value['bank_account_id'])

  if (isTransfer.value) {
    if (form.value['target_bank_account_id'])
      formData.append('target_bank_account_id', form.value['target_bank_account_id'])
    if (isDifferentCurrencyTransfer.value && form.value['exchange_rate'])
      formData.append('exchange_rate', form.value['exchange_rate'])
  }
  else {
    if (form.value['cash_session_id'])
      formData.append('cash_session_id', form.value['cash_session_id'])
  }

  if (form.value.amount)
    formData.append('amount', form.value.amount)
  if (form.value['operation_number'])
    formData.append('operation_number', form.value['operation_number'])
  if (form.value['operation_date'])
    formData.append('operation_date', form.value['operation_date'])
  if (form.value.notes)
    formData.append('notes', form.value.notes)

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
    max-width="640"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            :color="dialogColor"
            variant="tonal"
            class="me-2"
          >
            <VIcon
              :icon="dialogIcon"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>
        <VCardSubtitle>
          Gestione depósitos, retiros y transferencias entre cuentas
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
            <!-- Selector de tipo de operación -->
            <VCol cols="12">
              <VRadioGroup
                v-model="form.type"
                inline
                class="mt-0"
                @update:model-value="clearErrors"
              >
                <VRadio
                  label="Depósito (Caja → Banco)"
                  value="deposit"
                  color="success"
                />
                <VRadio
                  label="Retiro (Banco → Caja)"
                  value="withdrawal"
                  color="info"
                />
                <VRadio
                  label="Transferencia Bancaria"
                  value="transfer"
                  color="primary"
                />
              </VRadioGroup>
            </VCol>

            <!-- Casos Depósito y Retiro: Sesión de caja involucrada -->
            <VCol
              v-if="isDeposit || isWithdrawal"
              cols="12"
            >
              <VSelect
                v-model="form['cash_session_id']"
                :items="cashSessions"
                item-value="id"
                :item-title="s => `Sesión ${s.number} - ${s.box?.name || 'Caja'} (${s.opener?.name || 'Cajero'})`"
                :label="isDeposit ? 'Caja de origen (Efectivo disponible) *' : 'Caja de destino (Recepción) *'"
                placeholder="Seleccione la sesión de caja abierta"
                :loading="isLoadingOptions"
                :error-messages="errors.cash_session_id"
                @update:model-value="clearFieldError('cash_session_id')"
              />
            </VCol>

            <!-- Cuenta bancaria (o cuenta origen en transferencia) -->
            <VCol
              cols="12"
              :md="isTransfer ? 6 : 12"
            >
              <VAutocomplete
                v-model="form['bank_account_id']"
                :items="bankAccounts"
                item-value="id"
                :item-title="a => `${a.alias} - ${a.bank?.short_name || a.bank?.name || ''} (${a.currency?.code || ''})`"
                :label="isTransfer ? 'Cuenta origen *' : (isDeposit ? 'Cuenta bancaria de destino *' : 'Cuenta bancaria de origen *')"
                placeholder="Seleccione cuenta bancaria"
                :loading="isLoadingOptions"
                :error-messages="errors.bank_account_id"
                @update:model-value="clearFieldError('bank_account_id')"
              />
            </VCol>

            <!-- Cuenta bancaria destino (Solo transferencia) -->
            <VCol
              v-if="isTransfer"
              cols="12"
              md="6"
            >
              <VAutocomplete
                v-model="form['target_bank_account_id']"
                :items="availableTargetAccounts"
                item-value="id"
                :item-title="a => `${a.alias} - ${a.bank?.short_name || a.bank?.name || ''} (${a.currency?.code || ''})`"
                label="Cuenta destino *"
                placeholder="Seleccione cuenta destino"
                :loading="isLoadingOptions"
                :error-messages="errors.target_bank_account_id"
                @update:model-value="clearFieldError('target_bank_account_id')"
              />
            </VCol>

            <!-- Monto -->
            <VCol
              cols="12"
              :md="isDifferentCurrencyTransfer ? 6 : 6"
            >
              <VTextField
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                label="Monto *"
                placeholder="ej. 500.00"
                :prefix="selectedSourceAccount?.currency?.symbol || 'S/'"
                :error-messages="errors.amount"
                @update:model-value="clearFieldError('amount')"
              />
            </VCol>

            <!-- Tipo de cambio si las monedas son diferentes -->
            <VCol
              v-if="isDifferentCurrencyTransfer"
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['exchange_rate']"
                type="number"
                step="0.0001"
                min="0.0001"
                label="Tipo de cambio *"
                placeholder="ej. 3.7500"
                :error-messages="errors.exchange_rate"
                @update:model-value="clearFieldError('exchange_rate')"
              />
              <div
                v-if="targetAmount > 0"
                class="text-caption text-primary mt-1 font-weight-medium"
              >
                Monto a recibir en destino: {{ selectedTargetAccount?.currency?.symbol }} {{ targetAmount }}
              </div>
            </VCol>

            <!-- N° Operación bancaria -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['operation_number']"
                label="N° Operación / Voucher"
                placeholder="ej. OP-1234567"
                :error-messages="errors.operation_number"
                @update:model-value="clearFieldError('operation_number')"
              />
            </VCol>

            <!-- Fecha de operación -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form['operation_date']"
                type="date"
                label="Fecha de operación *"
                placeholder="ej. 2026-10-20"
                :error-messages="errors.operation_date"
                @update:model-value="clearFieldError('operation_date')"
              />
            </VCol>

            <!-- Voucher File -->
            <VCol cols="12">
              <VFileInput
                v-model="form.voucher"
                label="Comprobante / Voucher (opcional)"
                placeholder="Adjunte imagen o PDF de la operación"
                accept=".pdf,image/png,image/jpeg"
                show-size
                clearable
                :error-messages="errors.voucher"
                @update:model-value="clearFieldError('voucher')"
              />
            </VCol>

            <!-- Notas -->
            <VCol cols="12">
              <VTextarea
                v-model="form.notes"
                label="Observaciones o notas"
                placeholder="ej. Depósito de recaudación turno tarde"
                rows="2"
                :error-messages="errors.notes"
                @update:model-value="clearFieldError('notes')"
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
          :color="dialogColor"
          variant="elevated"
          :loading="isSubmitting"
          @click="handleSubmit"
        >
          <VIcon
            start
            icon="tabler-check"
          />
          Procesar operación
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
