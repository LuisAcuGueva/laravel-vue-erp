<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAcl } from '@/composables/useAcl'
import PageHeader from '@/components/common/PageHeader.vue'
import CurrenciesTab from '@/modules/finance/components/settings/CurrenciesTab.vue'
import ExchangeRatesTab from '@/modules/finance/components/settings/ExchangeRatesTab.vue'
import BanksTab from '@/modules/finance/components/settings/BanksTab.vue'
import BankAccountsTab from '@/modules/finance/components/settings/BankAccountsTab.vue'
import PaymentMethodsTab from '@/modules/finance/components/settings/PaymentMethodsTab.vue'
import FinancialAccountsTab from '@/modules/finance/components/settings/FinancialAccountsTab.vue'
import ConceptsTab from '@/modules/finance/components/settings/ConceptsTab.vue'

definePage({
  meta: {
    action: 'finance_settings.view',
    title: 'Configuración Financiera',
    icon: 'tabler-settings-dollar',
    description: 'Gestión de maestros de tesorería y finanzas: monedas, tipo de cambio, bancos, cuentas, métodos de pago, plan de cuentas y conceptos.',
  },
})

const { can } = useAcl()
const route = useRoute()
const router = useRouter()

const availableTabs = computed(() => {
  const tabs = []
  if (can('finance_settings.view')) {
    tabs.push({ id: 'currencies', label: 'Monedas', icon: 'tabler-currency-dollar' })
    tabs.push({ id: 'exchange-rates', label: 'Tipo de Cambio', icon: 'tabler-arrows-exchange' })
    tabs.push({ id: 'banks', label: 'Bancos', icon: 'tabler-building-bank' })
    tabs.push({ id: 'bank-accounts', label: 'Cuentas Bancarias', icon: 'tabler-wallet' })
    tabs.push({ id: 'payment-methods', label: 'Métodos de Pago', icon: 'tabler-credit-card' })
    tabs.push({ id: 'financial-accounts', label: 'Plan de Cuentas', icon: 'tabler-list-numbers' })
    tabs.push({ id: 'concepts', label: 'Conceptos', icon: 'tabler-receipt' })
  }

  return tabs
})

const currentTab = ref(null)

watch(() => route.query.tab, tabQuery => {
  if (tabQuery && availableTabs.value.some(tab => tab.id === tabQuery)) {
    currentTab.value = tabQuery
  }
  else if (!currentTab.value) {
    currentTab.value = availableTabs.value[0]?.id ?? null
  }
}, { immediate: true })

const onTabChange = tabId => {
  router.replace({ query: { ...route.query, tab: tabId } })
}
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VTabs
        v-model="currentTab"
        class="v-tabs-pill mx-6 mb-4"
        @update:model-value="onTabChange"
      >
        <VTab
          v-for="tab in availableTabs"
          :key="tab.id"
          :value="tab.id"
        >
          <VIcon
            start
            :icon="tab.icon"
          />
          {{ tab.label }}
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="currentTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <VWindowItem
          v-if="can('finance_settings.view')"
          value="currencies"
        >
          <CurrenciesTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="exchange-rates"
        >
          <ExchangeRatesTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="banks"
        >
          <BanksTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="bank-accounts"
        >
          <BankAccountsTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="payment-methods"
        >
          <PaymentMethodsTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="financial-accounts"
        >
          <FinancialAccountsTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('finance_settings.view')"
          value="concepts"
        >
          <ConceptsTab />
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Destino de los drawers de las pestañas -->
    <div id="finance-settings-drawers" />
  </div>
</template>
