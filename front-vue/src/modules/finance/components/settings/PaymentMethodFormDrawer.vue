<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { PAYMENT_METHOD_KINDS, SETTLEMENT_TYPES } from '../../utils/constants'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  methodData: {
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

const isEditing = computed(() => Boolean(props.methodData?.id))
const isSystem = computed(() => Boolean(props.methodData?.is_system))

const defaultForm = {
  code: '',
  name: '',
  kind: 'wallet',
  settlement: 'cash_box',
  'bank_account_id': null,
  'requires_reference': false,
  'sunat_code': '',
  'sort_order': 0,
  'is_active': true,
}

const form = ref({ ...defaultForm })

const bankAccountOptions = computed(() => {
  return financeStore.bankAccountsOptions.map(acc => ({
    value: acc.id,
    title: `${acc.alias} (${acc.bank?.short_name} ${acc.currency?.code} - ${acc.account_number})`,
  }))
})

onMounted(() => {
  financeStore.fetchBankAccountsOptions()
})

watch(() => form.value.kind, newKind => {
  if (newKind === 'cash') {
    form.value.settlement = 'cash_box'
    form.value['bank_account_id'] = null
  }
})

watch(() => form.value.settlement, newSettlement => {
  if (newSettlement !== 'bank_account') {
    form.value['bank_account_id'] = null
  }
})

watch(() => [props.isDrawerOpen, props.methodData], () => {
  const val = props.methodData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      kind: val.kind?.value || val.kind || 'wallet',
      settlement: val.settlement?.value || val.settlement || 'cash_box',
      'bank_account_id': val.bank_account_id || val.bank_account?.id || null,
      'requires_reference': Boolean(val.requires_reference),
      'sunat_code': val.sunat_code || '',
      'sort_order': val.sort_order !== undefined ? Number(val.sort_order) : 0,
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
    id: props.methodData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    kind: form.value.kind,
    settlement: form.value.settlement,
    'bank_account_id': form.value.settlement === 'bank_account' ? form.value['bank_account_id'] : null,
    'requires_reference': Boolean(form.value['requires_reference']),
    'sunat_code': form.value['sunat_code']?.trim() || null,
    'sort_order': Number(form.value['sort_order'] || 0),
    'is_active': Boolean(form.value['is_active']),
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
      :title="isEditing ? 'Editar Método de Pago' : 'Nuevo Método de Pago'"
      icon="tabler-credit-card"
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
              <VTextField
                v-model="form.code"
                label="Código *"
                placeholder="YAPE_POS"
                maxlength="20"
                :disabled="isSystem"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Yape Comercial"
                maxlength="60"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.kind"
                label="Tipo *"
                placeholder="Billetera Digital"
                :items="PAYMENT_METHOD_KINDS"
                :disabled="isSystem"
                :error-messages="errors.kind"
                @update:model-value="clearFieldError('kind')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.settlement"
                label="Liquidación *"
                placeholder="Caja"
                :items="SETTLEMENT_TYPES"
                :disabled="isSystem || form.kind === 'cash'"
                :error-messages="errors.settlement"
                @update:model-value="clearFieldError('settlement')"
              />
            </VCol>

            <VCol
              v-if="form.settlement === 'bank_account'"
              cols="12"
            >
              <VSelect
                v-model="form.bank_account_id"
                label="Cuenta Bancaria de Destino *"
                placeholder="Seleccione cuenta bancaria"
                :items="bankAccountOptions"
                :error-messages="errors.bank_account_id"
                @update:model-value="clearFieldError('bank_account_id')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.sunat_code"
                label="Código SUNAT"
                placeholder="008"
                maxlength="5"
                :error-messages="errors.sunat_code"
                @input="clearFieldError('sunat_code')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.sort_order"
                type="number"
                min="0"
                label="Orden de Aparición"
                placeholder="10"
                :error-messages="errors.sort_order"
                @input="clearFieldError('sort_order')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.requires_reference"
                label="Requiere Número de Operación / Referencia"
                color="primary"
                :error-messages="errors.requires_reference"
                @update:model-value="clearFieldError('requires_reference')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Método de Pago' }}
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
