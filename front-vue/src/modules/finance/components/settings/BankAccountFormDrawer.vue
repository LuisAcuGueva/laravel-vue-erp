<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { branchesApi } from '@/modules/maintenance/services/branchesApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { BANK_ACCOUNT_TYPES } from '../../utils/constants'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  accountData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const financeStore = useFinanceSettingsStore()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.accountData?.id))
const branchOptions = ref([])

const defaultForm = {
  'bank_id': null,
  'currency_id': null,
  'branch_id': null,
  alias: '',
  'account_number': '',
  cci: '',
  'account_type': 'checking',
  'holder_name': '',
  'is_active': true,
}

const form = ref({ ...defaultForm })

const bankOptions = computed(() => {
  return financeStore.banksOptions.map(b => ({
    value: b.id,
    title: `${b.short_name} - ${b.name}`,
  }))
})

const currencyOptions = computed(() => {
  return financeStore.currenciesOptions.map(c => ({
    value: c.id,
    title: `${c.code} (${c.symbol}) - ${c.name}`,
  }))
})

onMounted(async () => {
  financeStore.fetchBanksOptions()
  financeStore.fetchCurrenciesOptions()
  try {
    const res = await branchesApi.options()

    branchOptions.value = (res?.data || []).map(b => ({
      value: b.id,
      title: b.name,
    }))
  }
  catch {
    // silently catch if branches options fail
  }
})

watch(() => [props.isDrawerOpen, props.accountData], () => {
  const val = props.accountData

  form.value = val
    ? {
      'bank_id': val.bank_id || val.bank?.id || null,
      'currency_id': val.currency_id || val.currency?.id || null,
      'branch_id': val.branch_id || val.branch?.id || null,
      alias: val.alias || '',
      'account_number': val.account_number || '',
      cci: val.cci || '',
      'account_type': val.account_type?.value || val.account_type || 'checking',
      'holder_name': val.holder_name || '',
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.accountData?.id,
    'bank_id': form.value.bank_id,
    'currency_id': form.value.currency_id,
    'branch_id': form.value.branch_id || null,
    alias: form.value.alias.trim(),
    'account_number': form.value.account_number.trim(),
    cci: form.value.cci?.trim() || null,
    'account_type': form.value.account_type,
    'holder_name': form.value.holder_name?.trim() || null,
    'is_active': Boolean(form.value.is_active),
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="440"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Cuenta Bancaria' : 'Nueva Cuenta Bancaria'"
      icon="tabler-building-bank"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
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
            <VCol cols="12">
              <VSelect
                v-model="form.bank_id"
                label="Banco *"
                placeholder="BCP"
                :items="bankOptions"
                :error-messages="errors.bank_id"
                @update:model-value="clearFieldError('bank_id')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.currency_id"
                label="Moneda *"
                placeholder="PEN"
                :items="currencyOptions"
                :error-messages="errors.currency_id"
                @update:model-value="clearFieldError('currency_id')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.account_type"
                label="Tipo de Cuenta *"
                placeholder="Cuenta Corriente"
                :items="BANK_ACCOUNT_TYPES"
                :error-messages="errors.account_type"
                @update:model-value="clearFieldError('account_type')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.alias"
                label="Alias de la Cuenta *"
                placeholder="Cta Cte Soles Principal"
                maxlength="60"
                :error-messages="errors.alias"
                @input="clearFieldError('alias')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.account_number"
                label="Número de Cuenta *"
                placeholder="193-12345678-0-12"
                maxlength="30"
                :error-messages="errors.account_number"
                @input="clearFieldError('account_number')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.cci"
                label="Código de Cuenta Interbancario (CCI)"
                placeholder="00219300123456780012"
                maxlength="20"
                :error-messages="errors.cci"
                @input="clearFieldError('cci')"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.branch_id"
                label="Sede Asociada (Opcional)"
                placeholder="Sede Central"
                :items="branchOptions"
                clearable
                :error-messages="errors.branch_id"
                @update:model-value="clearFieldError('branch_id')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.holder_name"
                label="Titular de la Cuenta"
                placeholder="Mi Empresa S.A.C."
                maxlength="150"
                :error-messages="errors.holder_name"
                @input="clearFieldError('holder_name')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :error-messages="errors.is_active"
                @update:model-value="clearFieldError('is_active')"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Cuenta Bancaria' }}
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="props.isSubmitting"
                @click="handleClose"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VNavigationDrawer>
</template>
