<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useServerTable } from '@/composables/useServerTable'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { usePurchaseReceiptsStore } from '@/modules/purchases/stores/purchaseReceiptsStore'
import {
  RECEIPT_STATUS_OPTIONS,
  providerDocumentTypeTitle,
  receiptStatusColor,
  receiptStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'purchase_receipts.view',
    title: 'Recepciones de compra',
    icon: 'tabler-truck-loading',
    description: 'Recepción de mercadería de órdenes de compra, control de guías y entrada a almacén',
  },
})

const router = useRouter()
const store = usePurchaseReceiptsStore()
const { can } = useAcl()

const warehouses = ref([])

const table = useServerTable({
  fetch: params => store.fetchReceipts(params),
  filters: {
    status: null,
    provider_id: null,
    warehouse_id: null,
    date_from: null,
    date_to: null,
  },
})

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'receipt_date', sortable: false },
  { title: 'ORDEN DE COMPRA', key: 'order', sortable: false },
  { title: 'PROVEEDOR', key: 'provider', sortable: false },
  { title: 'ALMACÉN', key: 'warehouse', sortable: false },
  { title: 'DOC. PROVEEDOR', key: 'provider_document', sortable: false },
  { title: 'TOTAL COSTO', key: 'total_cost', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openDetail = receipt => router.push({ name: 'purchases-receipts-id', params: { id: receipt.id } })

onMounted(async () => {
  try {
    const res = await warehousesApi.options()

    warehouses.value = res?.data || []
  }
  catch (err) {
    console.error('Error al cargar almacenes', err)
  }
})
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
        table-id="purchase-receipts"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="RC-000001 o T001-12345"
        create-label="Nueva recepción"
        :creatable="can('purchase_receipts.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'purchases-receipts-new' })"
      >
        <template #filters>
          <ProviderPicker
            v-model="table.filters.provider_id"
            density="compact"
            class="purchases-filter-provider"
          />

          <div class="purchases-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
            <VSelect
              v-model="table.filters.status"
              :items="RECEIPT_STATUS_OPTIONS"
              label="Estado"
              placeholder="Confirmada"
              density="compact"
              clearable
            />
            <VSelect
              v-model="table.filters.warehouse_id"
              :items="warehouses"
              item-title="name"
              item-value="id"
              label="Almacén"
              placeholder="Almacén Central"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_from"
              label="Desde"
              placeholder="2026-01-01"
              type="date"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_to"
              label="Hasta"
              placeholder="2026-12-31"
              type="date"
              density="compact"
              clearable
            />
          </div>
        </template>

        <template #item.number="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.number }}</span>
        </template>

        <template #item.receipt_date="{ item }">
          <span>{{ formatDateDMY(item.receipt_date) }}</span>
        </template>

        <template #item.order="{ item }">
          <RouterLink
            v-if="item.order"
            :to="{ name: 'purchases-orders-id', params: { id: item.order.id } }"
            class="font-weight-medium text-primary text-decoration-none"
          >
            {{ item.order.number }}
          </RouterLink>
          <span v-else>—</span>
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
            <span class="text-caption text-medium-emphasis">{{ item.provider?.document_type }} {{ item.provider?.document_number }}</span>
          </div>
        </template>

        <template #item.warehouse="{ item }">
          {{ item.warehouse ? `${item.warehouse.code} - ${item.warehouse.name}` : '—' }}
        </template>

        <template #item.provider_document="{ item }">
          <div
            v-if="item.supplier_invoices?.length"
            class="d-flex flex-wrap gap-4"
          >
            <RouterLink
              v-for="invoice in item.supplier_invoices"
              :key="invoice.id"
              :to="{ name: 'purchases-supplier-invoices-id', params: { id: invoice.id } }"
              class="d-flex flex-column text-decoration-none"
            >
              <span class="font-weight-medium font-monospace text-primary">{{ invoice.document_label }}</span>
              <span class="text-caption text-medium-emphasis">{{ invoice.number }}</span>
            </RouterLink>
          </div>
          <div
            v-else
            class="d-flex flex-column"
          >
            <span>{{ providerDocumentTypeTitle(item.provider_document_type) }}</span>
            <span
              v-if="item.provider_document_number"
              class="text-caption text-medium-emphasis font-monospace"
            >
              {{ item.provider_document_number }}
            </span>
          </div>
        </template>

        <template #item.total_cost="{ item }">
          <span class="font-weight-medium">{{ formatAmount(item.total_cost, 'S/') }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            class="text-uppercase"
            :color="receiptStatusColor(item.status)"
          >
            {{ receiptStatusTitle(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              tooltip="Ver recepción"
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

