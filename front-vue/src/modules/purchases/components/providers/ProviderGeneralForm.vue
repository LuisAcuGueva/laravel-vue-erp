<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import UbigeoSelect from '@/components/common/UbigeoSelect.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'

/**
 * Formulario de datos generales del proveedor. Lo comparten el drawer de alta rápida
 * y la pestaña «General» del detalle.
 */
const props = defineProps({
  provider: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
  submitLabel: {
    type: String,
    default: 'Guardar',
  },
  showCancel: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'cancel'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const documentTypes = [
  { title: 'RUC', value: 'RUC' },
  { title: 'DNI', value: 'DNI' },
  { title: 'Carné de Extranjería (CE)', value: 'CE' },
  { title: 'Pasaporte', value: 'PASSPORT' },
  { title: 'Otro', value: 'OTHER' },
]

const documentLength = { RUC: 11, DNI: 8 }

const currencyOptions = ref([])

const defaultForm = () => ({
  document_type: 'RUC',
  document_number: '',
  business_name: '',
  trade_name: '',
  address: '',
  ubigeo: null,
  email: '',
  phone: '',
  website: '',
  default_currency_id: null,
  credit_days: 0,
  notes: '',
  is_active: true,
})

const form = ref(defaultForm())

onMounted(async () => {
  try {
    const res = await currenciesApi.options()

    currencyOptions.value = (res?.data || []).map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id }))
  }
  catch {
    currencyOptions.value = []
  }
})

watch(
  () => props.provider,
  val => {
    form.value = val
      ? {
        document_type: val.document_type || 'RUC',
        document_number: val.document_number || '',
        business_name: val.business_name || '',
        trade_name: val.trade_name || '',
        address: val.address || '',
        ubigeo: val.ubigeo || null,
        email: val.email || '',
        phone: val.phone || '',
        website: val.website || '',
        default_currency_id: val.default_currency_id || null,
        credit_days: val.credit_days ?? 0,
        notes: val.notes || '',
        is_active: val.is_active !== undefined ? Boolean(val.is_active) : true,
      }
      : defaultForm()
    clearErrors()
  },
  { immediate: true },
)

const handleSubmit = () => {
  if (props.readonly)
    return

  clearErrors()

  emit('submit', {
    document_type: form.value.document_type,
    document_number: form.value.document_number.trim(),
    business_name: form.value.business_name.trim(),
    trade_name: form.value.trade_name?.trim() || null,
    address: form.value.address?.trim() || null,
    ubigeo: form.value.ubigeo || null,
    email: form.value.email?.trim() || null,
    phone: form.value.phone?.trim() || null,
    website: form.value.website?.trim() || null,
    default_currency_id: form.value.default_currency_id || null,
    credit_days: Number(form.value.credit_days ?? 0),
    notes: form.value.notes?.trim() || null,
    is_active: form.value.is_active,
  })
}

defineExpose({ setErrors, clearErrors })
</script>

<template>
  <VForm @submit.prevent="handleSubmit">
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        sm="5"
      >
        <VSelect
          v-model="form.document_type"
          label="Tipo de documento *"
          :items="documentTypes"
          :readonly="props.readonly"
          :error-messages="errors.document_type"
          @update:model-value="clearFieldError('document_type')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="7"
      >
        <VTextField
          v-model="form.document_number"
          label="Nro. documento *"
          placeholder="20123456789"
          :maxlength="documentLength[form.document_type] || 15"
          :readonly="props.readonly"
          :error-messages="errors.document_number"
          @input="clearFieldError('document_number')"
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.business_name"
          label="Razón social *"
          placeholder="Distribuidora Andina S.A.C."
          maxlength="200"
          :readonly="props.readonly"
          :error-messages="errors.business_name"
          @input="clearFieldError('business_name')"
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.trade_name"
          label="Nombre comercial"
          placeholder="Andina"
          maxlength="200"
          :readonly="props.readonly"
          :error-messages="errors.trade_name"
          @input="clearFieldError('trade_name')"
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.address"
          label="Dirección"
          placeholder="Av. Los Olivos 456"
          maxlength="255"
          :readonly="props.readonly"
          :error-messages="errors.address"
          @input="clearFieldError('address')"
        />
      </VCol>

      <VCol cols="12">
        <UbigeoSelect
          v-model="form.ubigeo"
          :readonly="props.readonly"
          :error-messages="errors.ubigeo"
          @update:model-value="clearFieldError('ubigeo')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.email"
          label="Correo electrónico"
          placeholder="ventas@andina.pe"
          type="email"
          :readonly="props.readonly"
          :error-messages="errors.email"
          @input="clearFieldError('email')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="form.phone"
          label="Teléfono"
          placeholder="987 654 321"
          :readonly="props.readonly"
          :error-messages="errors.phone"
          @input="clearFieldError('phone')"
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.website"
          label="Sitio web"
          placeholder="https://www.andina.pe"
          :readonly="props.readonly"
          :error-messages="errors.website"
          @input="clearFieldError('website')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VSelect
          v-model="form.default_currency_id"
          label="Moneda por defecto"
          placeholder="PEN (S/)"
          :items="currencyOptions"
          clearable
          :readonly="props.readonly"
          :error-messages="errors.default_currency_id"
          @update:model-value="clearFieldError('default_currency_id')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model.number="form.credit_days"
          label="Días de crédito *"
          placeholder="30"
          type="number"
          min="0"
          max="365"
          hint="0 = contado"
          persistent-hint
          :readonly="props.readonly"
          :error-messages="errors.credit_days"
          @input="clearFieldError('credit_days')"
        />
      </VCol>

      <VCol cols="12">
        <VTextarea
          v-model="form.notes"
          label="Notas"
          placeholder="Entrega en 48 horas, atiende de lunes a sábado"
          rows="3"
          maxlength="2000"
          :readonly="props.readonly"
          :error-messages="errors.notes"
          @input="clearFieldError('notes')"
        />
      </VCol>

      <VCol cols="12">
        <VSwitch
          v-model="form.is_active"
          :readonly="props.readonly"
          label="Proveedor activo"
          density="compact"
        />
      </VCol>

      <VCol
        v-if="!props.readonly"
        cols="12"
        class="d-flex gap-4 mt-2"
      >
        <VBtn
          type="submit"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting"
          prepend-icon="tabler-device-floppy"
        >
          {{ props.submitLabel }}
        </VBtn>
        <VBtn
          v-if="props.showCancel"
          variant="tonal"
          color="secondary"
          :disabled="props.isSubmitting"
          @click="emit('cancel')"
        >
          Cancelar
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>
