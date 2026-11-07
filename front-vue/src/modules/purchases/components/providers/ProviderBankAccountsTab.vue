<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { banksApi, currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import { providersApi } from '@/modules/purchases/services/providersApi'

const props = defineProps({
  provider: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['refresh'])

const { can } = useAcl()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const rows = ref([])
const isSaving = ref(false)
const bankOptions = ref([])
const currencyOptions = ref([])

const toRows = provider => (provider.bank_accounts || []).map(a => ({
  id: a.id,
  bank_id: a.bank_id,
  currency_id: a.currency_id,
  account_number: a.account_number || '',
  cci: a.cci || '',
  is_detraction_account: Boolean(a.is_detraction_account),
}))

watch(() => props.provider, provider => {
  rows.value = toRows(provider)
  clearErrors()
}, { immediate: true })

onMounted(async () => {
  try {
    const [banksRes, currenciesRes] = await Promise.all([banksApi.options(), currenciesApi.options()])

    bankOptions.value = (banksRes?.data || []).map(b => ({ title: b.short_name || b.name, value: b.id }))
    currencyOptions.value = (currenciesRes?.data || []).map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id }))
  }
  catch {
    notify.error('No se pudieron cargar los bancos y monedas.')
  }
})

const addRow = () => {
  rows.value.push({
    id: null,
    bank_id: null,
    currency_id: props.provider.default_currency_id || null,
    account_number: '',
    cci: '',
    is_detraction_account: false,
  })
}

const removeRow = index => {
  rows.value.splice(index, 1)
}

const save = async () => {
  isSaving.value = true
  clearErrors()
  try {
    await providersApi.syncBankAccounts(props.provider.id, rows.value.map(row => ({
      id: row.id,
      bank_id: row.bank_id,
      currency_id: row.currency_id,
      account_number: row.account_number.trim(),
      cci: row.cci?.trim() || null,
      is_detraction_account: row.is_detraction_account,
    })))
    notify.success('Cuentas bancarias actualizadas correctamente')
    emit('refresh')
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VCardText>
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VTable density="comfortable">
      <thead>
        <tr>
          <th>BANCO *</th>
          <th>MONEDA *</th>
          <th>NRO. DE CUENTA *</th>
          <th>CCI</th>
          <th class="text-center">
            DETRACCIÓN
          </th>
          <th
            v-if="can('providers.update')"
            class="text-center"
          >
            ACCIONES
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!rows.length">
          <td
            colspan="6"
            class="text-center text-medium-emphasis py-6"
          >
            Aún no hay cuentas bancarias registradas.
          </td>
        </tr>
        <tr
          v-for="(row, index) in rows"
          :key="row.id || `new-${index}`"
        >
          <td>
            <VAutocomplete
              v-model="row.bank_id"
              :items="bankOptions"
              label="Banco *"
              placeholder="BCP"
              density="compact"
              :readonly="!can('providers.update')"
              :error-messages="errors[`accounts.${index}.bank_id`]"
            />
          </td>
          <td>
            <VSelect
              v-model="row.currency_id"
              :items="currencyOptions"
              label="Moneda *"
              placeholder="PEN (S/)"
              density="compact"
              :readonly="!can('providers.update')"
              :error-messages="errors[`accounts.${index}.currency_id`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.account_number"
              label="Nro. de cuenta *"
              placeholder="191-1234567-0-12"
              density="compact"
              maxlength="30"
              :readonly="!can('providers.update')"
              :error-messages="errors[`accounts.${index}.account_number`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.cci"
              label="CCI"
              placeholder="00219100123456701234"
              density="compact"
              maxlength="20"
              :readonly="!can('providers.update')"
              :error-messages="errors[`accounts.${index}.cci`]"
            />
          </td>
          <td class="text-center">
            <VSwitch
              v-model="row.is_detraction_account"
              density="compact"
              hide-details
              color="primary"
              :readonly="!can('providers.update')"
            />
          </td>
          <td
            v-if="can('providers.update')"
            class="text-center"
          >
            <RowActionButton
              action="delete"
              tooltip="Quitar cuenta"
              @click="removeRow(index)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-if="can('providers.update')"
      class="d-flex flex-wrap gap-4 mt-4"
    >
      <VBtn
        variant="tonal"
        prepend-icon="tabler-plus"
        :disabled="rows.length >= 10"
        @click="addRow"
      >
        Agregar cuenta
      </VBtn>
      <VBtn
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="isSaving"
        @click="save"
      >
        Guardar cuentas
      </VBtn>
    </div>
  </VCardText>
</template>
