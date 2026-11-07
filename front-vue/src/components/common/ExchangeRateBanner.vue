<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { exchangeRatesApi } from '@/modules/finance/services/financeSettingsApi'
import { useFinanceSettingsStore } from '@/modules/finance/stores/financeSettingsStore'

const { can } = useAcl()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const canManageRate = computed(() => can('exchange_rates.manage'))

const staleCurrencies = ref([])
const dismissed = reactive(new Set())
const forms = reactive({})

const checkRates = async () => {
  if (!canManageRate.value)
    return

  try {
    const currencies = await financeStore.fetchCurrenciesOptions()
    const foreignCurrencies = currencies.filter(c => !c.is_base && c.is_active)

    if (!foreignCurrencies.length)
      return

    const results = await Promise.all(foreignCurrencies.map(async currency => {
      try {
        const res = await exchangeRatesApi.current(currency.id)

        return { currency, data: res?.data }
      }
      catch {
        return { currency, data: null }
      }
    }))

    staleCurrencies.value = results
      .filter(({ data }) => data?.is_stale)
      .map(({ currency }) => currency)

    for (const currency of staleCurrencies.value) {
      const stale = results.find(r => r.currency.id === currency.id)

      forms[currency.id] = {
        buyRate: stale?.data?.rate ? String(stale.data.rate.buy_rate) : '',
        sellRate: stale?.data?.rate ? String(stale.data.rate.sell_rate) : '',
        error: '',
        submitting: false,
      }
    }
  }
  catch {
    // Silently ignore check errors in layout
  }
}

onMounted(() => {
  checkRates()
})

const handleSave = async currency => {
  const form = forms[currency.id]

  if (!form.buyRate || !form.sellRate) {
    form.error = 'Ingrese los valores de compra y venta'

    return
  }

  const buy = parseFloat(form.buyRate)
  const sell = parseFloat(form.sellRate)

  if (sell < buy) {
    form.error = 'El precio de venta debe ser mayor o igual al de compra'

    return
  }

  form.submitting = true
  form.error = ''
  try {
    const today = new Date().toISOString().split('T')[0]

    await exchangeRatesApi.upsert({
      'currency_id': currency.id,
      date: today,
      'buy_rate': buy,
      'sell_rate': sell,
    })

    notify.success('Tipo de cambio registrado correctamente')
    staleCurrencies.value = staleCurrencies.value.filter(c => c.id !== currency.id)
  }
  catch (err) {
    form.error = err?.data?.message || 'Error al guardar el tipo de cambio'
  }
  finally {
    form.submitting = false
  }
}
</script>

<template>
  <div
    v-if="canManageRate"
    class="mb-4"
  >
    <VAlert
      v-for="currency in staleCurrencies.filter(c => !dismissed.has(c.id))"
      :key="currency.id"
      type="warning"
      variant="tonal"
      closable
      icon="tabler-alert-triangle"
      class="mb-2"
      @click:close="dismissed.add(currency.id)"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div>
          <span class="font-weight-bold">Atención:</span>
          El tipo de cambio para <strong>{{ currency.code }}</strong> no está registrado para hoy.
          <span
            v-if="forms[currency.id]?.error"
            class="text-error ms-2"
          >({{ forms[currency.id].error }})</span>
        </div>

        <div class="d-flex align-center gap-2">
          <div style="inline-size: 110px;">
            <VTextField
              v-model="forms[currency.id].buyRate"
              type="number"
              step="0.0001"
              density="compact"
              label="Compra"
              placeholder="3.7450"
              hide-details
            />
          </div>

          <div style="inline-size: 110px;">
            <VTextField
              v-model="forms[currency.id].sellRate"
              type="number"
              step="0.0001"
              density="compact"
              label="Venta"
              placeholder="3.7550"
              hide-details
            />
          </div>

          <VBtn
            size="small"
            color="warning"
            :loading="forms[currency.id].submitting"
            @click="handleSave(currency)"
          >
            Guardar TC
          </VBtn>
        </div>
      </div>
    </VAlert>
  </div>
</template>
