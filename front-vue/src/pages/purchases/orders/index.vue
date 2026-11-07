<script setup>
/* eslint-disable camelcase */
import PageHeader from '@/components/common/PageHeader.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useServerTable } from '@/composables/useServerTable'
import { usePurchaseOrdersStore } from '@/modules/purchases/stores/purchaseOrdersStore'
import { ORDER_STATUS_OPTIONS, orderStatusColor, orderStatusTitle } from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'purchase_orders.view',
    title: 'Órdenes de compra',
    icon: 'tabler-receipt-2',
    description: 'Órdenes de compra a proveedores: emisión, aprobación y avance de recepción',
  },
})

const router = useRouter()
const store = usePurchaseOrdersStore()
const { can } = useAcl()

const table = useServerTable({
  fetch: params => store.fetchOrders(params),
  filters: { 'status': null, 'provider_id': null, 'date_from': null, 'date_to': null },
})

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'order_date', sortable: false },
  { title: 'PROVEEDOR', key: 'provider', sortable: false },
  { title: 'ALMACÉN', key: 'warehouse', sortable: false },
  { title: 'CONDICIÓN', key: 'payment_condition', sortable: false },
  { title: 'TOTAL', key: 'total', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openDetail = order => router.push({ name: 'purchases-orders-id', params: { id: order.id } })
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
        table-id="purchase-orders"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="OC-000001 o Distribuidora Andina"
        create-label="Nueva orden"
        :creatable="can('purchase_orders.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'purchases-orders-new' })"
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
              :items="ORDER_STATUS_OPTIONS"
              label="Estado"
              placeholder="Aprobada"
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

        <template #item.order_date="{ item }">
          <span>{{ formatDateDMY(item.order_date) }}</span>
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

        <template #item.payment_condition="{ item }">
          {{ item.payment_condition === 'credit' ? `Crédito ${item.credit_days} días` : 'Contado' }}
        </template>

        <template #item.total="{ item }">
          <span class="font-weight-medium">{{ formatAmount(item.total, item.currency?.symbol) }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            class="text-uppercase"
            :color="orderStatusColor(item.status)"
          >
            {{ orderStatusTitle(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              tooltip="Ver orden"
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

