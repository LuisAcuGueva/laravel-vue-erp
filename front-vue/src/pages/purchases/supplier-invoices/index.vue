<script setup>
/* eslint-disable camelcase */
import { useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useServerTable } from '@/composables/useServerTable'
import { useSupplierInvoicesStore } from '@/modules/purchases/stores/supplierInvoicesStore'
import {
  SUPPLIER_INVOICE_KIND_OPTIONS,
  SUPPLIER_INVOICE_STATUS_OPTIONS,
  supplierDocumentTypeTitle,
  supplierInvoiceKindTitle,
  supplierInvoiceStatusColor,
  supplierInvoiceStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'supplier_invoices.view',
    title: 'Facturas de proveedor',
    icon: 'tabler-receipt-tax',
    description: 'Facturas de compra y gastos directos, con su vencimiento, saldo y estado de pago',
  },
})

const router = useRouter()
const store = useSupplierInvoicesStore()
const { can } = useAcl()

const table = useServerTable({
  fetch: params => store.fetchInvoices(params),
  filters: {
    kind: null,
    provider_id: null,
    status: null,
    date_from: null,
    date_to: null,
  },
})

const headers = [
  { title: 'DOCUMENTO', key: 'document', sortable: false, alwaysVisible: true },
  { title: 'N.° INTERNO', key: 'number', sortable: false },
  { title: 'PROVEEDOR / BENEFICIARIO', key: 'provider', sortable: false },
  { title: 'TIPO', key: 'kind', sortable: false },
  { title: 'EMISIÓN', key: 'issue_date', sortable: false },
  { title: 'VENCIMIENTO', key: 'due_date', sortable: false },
  { title: 'TOTAL', key: 'total', sortable: false, align: 'end' },
  { title: 'SALDO', key: 'balance', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openDetail = invoice => router.push({ name: 'purchases-supplier-invoices-id', params: { id: invoice.id } })
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="supplier-invoices"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="FP-000001 o F001-000123"
        create-label="Nueva factura"
        :creatable="can('supplier_invoices.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'purchases-supplier-invoices-new' })"
      >
        <template #filters>
          <ProviderPicker
            v-model="table.filters.provider_id"
            density="compact"
            class="purchases-filter-provider"
          />

          <div class="purchases-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
            <VSelect
              v-model="table.filters.kind"
              :items="SUPPLIER_INVOICE_KIND_OPTIONS"
              label="Tipo"
              placeholder="Compra"
              density="compact"
              clearable
            />
            <VSelect
              v-model="table.filters.status"
              :items="SUPPLIER_INVOICE_STATUS_OPTIONS"
              label="Estado"
              placeholder="Pendiente"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_from"
              label="Emitida desde"
              placeholder="2026-01-01"
              type="date"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_to"
              label="Emitida hasta"
              placeholder="2026-12-31"
              type="date"
              density="compact"
              clearable
            />
          </div>
        </template>

        <template #item.document="{ item }">
          <div class="d-flex flex-column py-1">
            <RouterLink
              :to="{ name: 'purchases-supplier-invoices-id', params: { id: item.id } }"
              class="font-weight-medium font-monospace text-primary text-decoration-none"
            >
              {{ item.document_label }}
            </RouterLink>
            <span class="text-caption text-medium-emphasis">{{ supplierDocumentTypeTitle(item.document_type) }}</span>
          </div>
        </template>

        <template #item.number="{ item }">
          <span class="font-monospace">{{ item.number }}</span>
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

        <template #item.kind="{ item }">
          {{ supplierInvoiceKindTitle(item.kind) }}
        </template>

        <template #item.issue_date="{ item }">
          <span>{{ formatDateDMY(item.issue_date) }}</span>
        </template>

        <template #item.due_date="{ item }">
          <span :class="{ 'text-error font-weight-medium': item.is_overdue }">{{ formatDateDMY(item.due_date) }}</span>
        </template>

        <template #item.total="{ item }">
          {{ formatAmount(item.total, item.currency?.symbol) }}
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
              action="view"
              tooltip="Ver factura"
              @click="openDetail(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>
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

