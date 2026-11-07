<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import AgingTable from '@/modules/purchases/components/payables/AgingTable.vue'
import ProviderStatementDialog from '@/modules/purchases/components/payables/ProviderStatementDialog.vue'
import SupplierPaymentDialog from '@/modules/purchases/components/payables/SupplierPaymentDialog.vue'
import { accountsPayableApi } from '@/modules/purchases/services/accountsPayableApi'
import { supplierPaymentsApi } from '@/modules/purchases/services/supplierPaymentsApi'
import {
  SUPPLIER_PAYMENT_STATUS_OPTIONS,
  supplierInvoiceStatusColor,
  supplierInvoiceStatusTitle,
  supplierPaymentStatusColor,
  supplierPaymentStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: ['supplier_invoices.view', 'supplier_payments.view'],
    title: 'Cuentas por pagar',
    icon: 'tabler-calendar-due',
    description: 'Cuotas por pagar a proveedores, antigüedad de saldos, pagos realizados y estados de cuenta',
  },
})

const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { alert } = useConfirm()

const tabs = computed(() => [
  { value: 'installments', title: 'Cuotas por pagar', icon: 'tabler-calendar-dollar', visible: can('supplier_invoices.view') },
  { value: 'aging', title: 'Antigüedad', icon: 'tabler-chart-bar', visible: can('supplier_invoices.view') },
  { value: 'payments', title: 'Pagos', icon: 'tabler-cash', visible: can('supplier_payments.view') },
].filter(tab => tab.visible))

const activeTab = ref(tabs.value[0]?.value || 'installments')

const currencies = ref([])
const currencyOptions = computed(() => currencies.value.map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id })))

const OVERDUE_OPTIONS = [
  { title: 'Solo vencidas', value: true },
  { title: 'Por vencer', value: false },
]

// --- Cuotas por pagar ---
const totalsByCurrency = ref([])
const selected = ref([])
const isPaymentOpen = ref(false)

const installmentsTable = useServerTable({
  immediate: can('supplier_invoices.view'),
  fetch: async params => {
    const response = await accountsPayableApi.list(params)

    totalsByCurrency.value = response?.meta?.totals_by_currency || []

    return response
  },
  filters: {
    provider_id: null,
    currency_id: null,
    overdue: null,
    due_from: null,
    due_to: null,
  },

  // "Por vencer" no es un filtro de la API: se omite y se listan todas las cuotas abiertas.
  mapParams: params => (params.overdue === '0' ? { ...params, overdue: undefined } : params),
})

const installmentHeaders = [
  { title: 'VENCIMIENTO', key: 'due_date', sortable: false, alwaysVisible: true },
  { title: 'PROVEEDOR / BENEFICIARIO', key: 'provider', sortable: false },
  { title: 'DOCUMENTO', key: 'invoice', sortable: false },
  { title: 'CUOTA', key: 'number', sortable: false, align: 'center' },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'SALDO', key: 'balance', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

// Solo se pagan juntas cuotas del mismo proveedor (o beneficiario) y moneda.
const selectionKey = installment => `${installment.provider_id || installment.supplier_invoice_id}|${installment.currency_id}`

const selectionIsPayable = computed(() => selected.value.length > 0
  && new Set(selected.value.map(selectionKey)).size === 1)

const openPayment = (rows = selected.value) => {
  if (!rows.length)
    return

  if (new Set(rows.map(selectionKey)).size !== 1) {
    notify.error('Selecciona cuotas de un mismo proveedor y moneda.')

    return
  }

  selected.value = rows
  isPaymentOpen.value = true
}

const onPaid = () => {
  selected.value = []
  installmentsTable.reload()
  agingRef.value?.reload()
  if (can('supplier_payments.view'))
    paymentsTable.reload()
}

// --- Antigüedad ---
const agingRef = ref(null)
const agingAsOf = ref(null)

// --- Estado de cuenta ---
const statementTarget = ref({ provider: null, currency: null })
const isStatementOpen = ref(false)

const openStatement = ({ provider, currency }) => {
  statementTarget.value = { provider, currency }
  isStatementOpen.value = true
}

// --- Pagos ---
const paymentsTable = useServerTable({
  immediate: can('supplier_payments.view'),
  fetch: params => supplierPaymentsApi.list(params),
  filters: {
    provider_id: null,
    status: null,
    date_from: null,
    date_to: null,
  },
})

const paymentHeaders = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'payment_date', sortable: false },
  { title: 'PROVEEDOR / BENEFICIARIO', key: 'provider', sortable: false },
  { title: 'DOC. PROVEEDOR', key: 'invoice', sortable: false },
  { title: 'MÉTODO', key: 'method', sortable: false },
  { title: 'REFERENCIA', key: 'reference', sortable: false },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

// Una o dos cuotas pagadas juntas pueden pertenecer a la misma factura: se deduplica por id.
const uniqueInvoices = allocations => {
  const seen = new Map()

  for (const allocation of allocations) {
    if (allocation.invoice && !seen.has(allocation.invoice.id))
      seen.set(allocation.invoice.id, allocation.invoice)
  }

  return [...seen.values()]
}

const cancelTarget = ref(null)
const isCancelOpen = ref(false)
const isCancelling = ref(false)

const askCancel = payment => {
  cancelTarget.value = payment
  isCancelOpen.value = true
}

const handleCancel = async reason => {
  isCancelling.value = true
  isCancelOpen.value = false
  try {
    await supplierPaymentsApi.cancel(cancelTarget.value.id, reason)
    notify.success('Pago a proveedor anulado correctamente')
    onPaid()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo anular el pago.' })
  }
  finally {
    isCancelling.value = false
  }
}

onMounted(async () => {
  try {
    currencies.value = (await currenciesApi.options())?.data || []
  }
  catch {
    currencies.value = []
  }
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader>
          <template #actions>
            <VBtn
              v-if="can('supplier_invoices.create')"
              prepend-icon="tabler-plus"
              @click="router.push({ name: 'purchases-supplier-invoices-new' })"
            >
              Nueva factura
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>

      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            start
            :icon="tab.icon"
          />
          {{ tab.title }}
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <VWindowItem
          v-if="can('supplier_invoices.view')"
          value="installments"
        >
          <div
            v-if="totalsByCurrency.length"
            class="d-flex flex-wrap gap-3 pa-4 pb-0"
          >
            <VChip
              v-for="row in totalsByCurrency"
              :key="row.currency_id"
              color="primary"
              variant="tonal"
            >
              Por pagar {{ formatAmount(row.balance, row.symbol) }}
              <span
                v-if="Number(row.overdue) > 0"
                class="text-error ms-2"
              >
                · vencido {{ formatAmount(row.overdue, row.symbol) }}
              </span>
            </VChip>
          </div>

          <ServerDataTable
            v-model:options="installmentsTable.options"
            v-model:search="installmentsTable.search"
            v-model="selected"
            table-id="accounts-payable-installments"
            :headers="installmentHeaders"
            :items="installmentsTable.items"
            :total-items="installmentsTable.total"
            :loading="installmentsTable.loading"
            :has-filters="installmentsTable.hasFilters"
            search-placeholder="FP-000001, F001-000123 o razón social"
            show-select
            return-object
            item-value="id"
            @refresh="installmentsTable.reload"
          >
            <template #filters>
              <ProviderPicker
                v-model="installmentsTable.filters.provider_id"
                density="compact"
                class="purchases-filter-provider"
              />

              <div class="purchases-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
                <VSelect
                  v-model="installmentsTable.filters.currency_id"
                  :items="currencyOptions"
                  label="Moneda"
                  placeholder="PEN (S/)"
                  density="compact"
                  clearable
                />
                <VSelect
                  v-model="installmentsTable.filters.overdue"
                  :items="OVERDUE_OPTIONS"
                  label="Vencimiento"
                  placeholder="Solo vencidas"
                  density="compact"
                  clearable
                />
                <VTextField
                  v-model="installmentsTable.filters.due_from"
                  label="Vence desde"
                  placeholder="2026-01-01"
                  type="date"
                  density="compact"
                  clearable
                />
                <VTextField
                  v-model="installmentsTable.filters.due_to"
                  label="Vence hasta"
                  placeholder="2026-12-31"
                  type="date"
                  density="compact"
                  clearable
                />
              </div>
            </template>

            <template #toolbar-append>
              <VBtn
                v-if="can('supplier_payments.create')"
                color="primary"
                prepend-icon="tabler-cash"
                :disabled="!selectionIsPayable"
                class="me-2"
                @click="openPayment(selected)"
              >
                Pagar seleccionadas{{ selected.length ? ` (${selected.length})` : '' }}
              </VBtn>
            </template>

            <template #item.due_date="{ item }">
              <div class="d-flex flex-column">
                <span :class="{ 'text-error font-weight-medium': item.is_overdue }">{{ formatDateDMY(item.due_date) }}</span>
                <span
                  v-if="item.is_overdue"
                  class="text-caption text-error"
                >
                  {{ item.days_overdue }} días vencida
                </span>
              </div>
            </template>

            <template #item.provider="{ item }">
              <div class="d-flex flex-column py-1">
                <RouterLink
                  v-if="item.provider"
                  :to="{ name: 'purchases-providers-id', params: { id: item.provider.id } }"
                  class="font-weight-medium text-primary text-decoration-none"
                >
                  {{ item.provider.business_name }}
                </RouterLink>
                <span
                  v-else
                  class="font-weight-medium text-high-emphasis"
                >
                  {{ item.invoice?.counterparty_name }}
                </span>
                <span
                  v-if="item.provider"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item.provider.document_type }} {{ item.provider.document_number }}
                </span>
              </div>
            </template>

            <template #item.invoice="{ item }">
              <RouterLink
                :to="{ name: 'purchases-supplier-invoices-id', params: { id: item.supplier_invoice_id } }"
                class="d-flex flex-column text-decoration-none"
              >
                <span class="font-weight-medium font-monospace text-primary">{{ item.invoice?.document_label }}</span>
                <span class="text-caption text-medium-emphasis">{{ item.invoice?.number }}</span>
              </RouterLink>
            </template>

            <template #item.amount="{ item }">
              {{ formatAmount(item.amount, item.currency?.symbol) }}
            </template>

            <template #item.balance="{ item }">
              <span class="font-weight-medium">{{ formatAmount(item.balance, item.currency?.symbol) }}</span>
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                class="text-uppercase"
                :color="item.is_overdue ? 'error' : supplierInvoiceStatusColor(item.status)"
              >
                {{ item.is_overdue ? 'Vencida' : supplierInvoiceStatusTitle(item.status) }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex justify-center gap-2">
                <RowActionButton
                  v-if="can('supplier_payments.create')"
                  action="custom"
                  icon="tabler-cash"
                  tooltip="Pagar cuota"
                  @click="openPayment([item])"
                />
                <RowActionButton
                  v-if="item.provider"
                  action="custom"
                  icon="tabler-file-text"
                  color="info"
                  tooltip="Estado de cuenta"
                  @click="openStatement({ provider: item.provider, currency: item.currency })"
                />
              </div>
            </template>
          </ServerDataTable>
        </VWindowItem>

        <VWindowItem
          v-if="can('supplier_invoices.view')"
          value="aging"
        >
          <VCardText>
            <VRow class="mb-2">
              <VCol
                cols="12"
                sm="4"
                md="3"
              >
                <VTextField
                  v-model="agingAsOf"
                  label="Fecha de corte"
                  placeholder="2026-10-20"
                  type="date"
                  density="compact"
                  clearable
                />
              </VCol>
            </VRow>
            <AgingTable
              ref="agingRef"
              :as-of="agingAsOf"
              @statement="openStatement"
            />
          </VCardText>
        </VWindowItem>

        <VWindowItem
          v-if="can('supplier_payments.view')"
          value="payments"
        >
          <ServerDataTable
            v-model:options="paymentsTable.options"
            v-model:search="paymentsTable.search"
            table-id="supplier-payments"
            :headers="paymentHeaders"
            :items="paymentsTable.items"
            :total-items="paymentsTable.total"
            :loading="paymentsTable.loading"
            :has-filters="paymentsTable.hasFilters"
            search-placeholder="PP-000001 o N.° de operación"
            @refresh="paymentsTable.reload"
          >
            <template #filters>
              <ProviderPicker
                v-model="paymentsTable.filters.provider_id"
                density="compact"
                class="purchases-filter-provider"
              />

              <div class="purchases-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
                <VSelect
                  v-model="paymentsTable.filters.status"
                  :items="SUPPLIER_PAYMENT_STATUS_OPTIONS"
                  label="Estado"
                  placeholder="Registrado"
                  density="compact"
                  clearable
                />
                <VTextField
                  v-model="paymentsTable.filters.date_from"
                  label="Desde"
                  placeholder="2026-01-01"
                  type="date"
                  density="compact"
                  clearable
                />
                <VTextField
                  v-model="paymentsTable.filters.date_to"
                  label="Hasta"
                  placeholder="2026-12-31"
                  type="date"
                  density="compact"
                  clearable
                />
              </div>
            </template>

            <template #item.number="{ item }">
              <RouterLink
                :to="{ name: 'purchases-supplier-payments-id', params: { id: item.id } }"
                class="font-weight-medium font-monospace text-primary text-decoration-none"
              >
                {{ item.number }}
              </RouterLink>
            </template>

            <template #item.payment_date="{ item }">
              <span>{{ formatDateDMY(item.payment_date) }}</span>
            </template>

            <template #item.provider="{ item }">
              <div class="d-flex flex-column py-1">
                <RouterLink
                  v-if="item.provider"
                  :to="{ name: 'purchases-providers-id', params: { id: item.provider.id } }"
                  class="font-weight-medium text-primary text-decoration-none"
                >
                  {{ item.provider.business_name }}
                </RouterLink>
                <span
                  v-else
                  class="font-weight-medium text-high-emphasis"
                >
                  {{ item.counterparty_name }}
                </span>
                <span
                  v-if="item.provider"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item.provider.document_type }} {{ item.provider.document_number }}
                </span>
              </div>
            </template>

            <template #item.invoice="{ item }">
              <div
                v-if="item.allocations?.length"
                class="d-flex flex-wrap gap-4"
              >
                <RouterLink
                  v-for="invoice in uniqueInvoices(item.allocations)"
                  :key="invoice.id"
                  :to="{ name: 'purchases-supplier-invoices-id', params: { id: invoice.id } }"
                  class="d-flex flex-column text-decoration-none"
                >
                  <span class="font-weight-medium font-monospace text-primary">{{ invoice.document_label }}</span>
                  <span class="text-caption text-medium-emphasis">{{ invoice.number }}</span>
                </RouterLink>
              </div>
              <span v-else>—</span>
            </template>

            <template #item.method="{ item }">
              {{ item.payment_method?.name }}
            </template>

            <template #item.reference="{ item }">
              {{ item.reference || '—' }}
            </template>

            <template #item.amount="{ item }">
              <span class="font-weight-medium">{{ formatAmount(item.amount, item.currency?.symbol) }}</span>
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                class="text-uppercase"
                :color="supplierPaymentStatusColor(item.status)"
              >
                {{ supplierPaymentStatusTitle(item.status) }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex justify-center gap-2">
                <RowActionButton
                  action="view"
                  tooltip="Ver pago"
                  @click="router.push({ name: 'purchases-supplier-payments-id', params: { id: item.id } })"
                />
                <RowActionButton
                  v-if="item.status === 'completed' && can('supplier_payments.cancel')"
                  action="custom"
                  icon="tabler-ban"
                  color="error"
                  tooltip="Anular pago"
                  :disabled="isCancelling"
                  @click="askCancel(item)"
                />
              </div>
            </template>
          </ServerDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <SupplierPaymentDialog
      v-model="isPaymentOpen"
      :installments="selected"
      @paid="onPaid"
    />

    <ProviderStatementDialog
      v-model="isStatementOpen"
      :provider="statementTarget.provider"
      :currency="statementTarget.currency"
    />

    <CancelDocumentDialog
      v-model="isCancelOpen"
      :title="`Anular pago ${cancelTarget?.number || ''}`"
      :document-number="cancelTarget?.number || ''"
      description="Se revertirá el movimiento de caja o banco del pago y se restaurará el saldo de las cuotas. La sesión de caja o el periodo bancario deben seguir abiertos."
      reason-label="Motivo de anulación *"
      reason-placeholder="Ej. Pago registrado dos veces"
      confirm-label="Confirmar anulación"
      color="error"
      required-message="El motivo de anulación es obligatorio."
      :loading="isCancelling"
      @confirm="handleCancel"
    />
  </div>
</template>

<style scoped>
.purchases-filter-provider {
  flex: 2 1 22rem !important;
  min-inline-size: 16rem !important;
  max-inline-size: 38rem !important;

  :deep(.v-input) {
    inline-size: 100%;
  }
}

.purchases-filters-row-2 {
  flex-basis: 100%;

  :deep(.v-input) {
    flex: 1 1 9rem;
    min-inline-size: 8rem;
    max-inline-size: 14rem;
  }
}

@media (max-width: 599.98px) {
  .purchases-filter-provider {
    max-inline-size: none !important;
    inline-size: 100% !important;
  }

  .purchases-filters-row-2 {
    flex-direction: column;
    align-items: stretch !important;

    :deep(.v-input) {
      max-inline-size: none;
      inline-size: 100%;
    }
  }
}
</style>

