<script setup>
import { computed, ref, watch } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useServerTable } from '@/composables/useServerTable'
import { usePurchaseRequestsStore } from '@/modules/purchases/stores/purchaseRequestsStore'
import { PRIORITY_OPTIONS, REQUEST_STATUS_OPTIONS, requestStatusColor, requestStatusTitle } from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'

definePage({
  meta: {
    action: 'purchase_requests.view',
    title: 'Requerimientos',
    icon: 'tabler-clipboard-list',
    description: 'Pedidos de compra de las áreas: creación, envío y aprobación',
  },
})

const router = useRouter()
const store = usePurchaseRequestsStore()
const { can } = useAcl()

// «Por aprobar» solo lo ve quien puede aprobar; «Mis requerimientos», quien puede ver.
const tabs = computed(() => {
  const list = [{ id: 'mine', label: 'Mis requerimientos', icon: 'tabler-user' }]

  if (can('purchase_requests.approve'))
    list.push({ id: 'approve', label: 'Por aprobar', icon: 'tabler-checklist' })
  list.push({ id: 'all', label: 'Todos', icon: 'tabler-list' })

  return list
})

const currentTab = ref('mine')

const table = useServerTable({
  fetch: params => store.fetchRequests(params),
  filters: { 'mine': true, 'status': null, 'priority': null },
})

watch(currentTab, tab => {
  table.filters.mine = tab === 'mine' ? true : null
  table.filters.status = tab === 'approve' ? 'submitted' : null
})

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'ÁREA', key: 'area', sortable: false },
  { title: 'ALMACÉN', key: 'warehouse', sortable: false },
  { title: 'SOLICITANTE', key: 'requester', sortable: false },
  { title: 'FECHA REQUERIDA', key: 'required_date', sortable: false },
  { title: 'ÍTEMS', key: 'items_count', sortable: false, align: 'center' },
  { title: 'PRIORIDAD', key: 'priority', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openDetail = request => router.push({ name: 'purchases-requests-id', params: { id: request.id } })
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
      >
        <VTab
          v-for="tab in tabs"
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

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="purchase-requests"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters && currentTab === 'all'"
        search-placeholder="RQ-000001"
        create-label="Nuevo requerimiento"
        :creatable="can('purchase_requests.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'purchases-requests-new' })"
      >
        <template #filters>
          <VSelect
            v-if="currentTab !== 'approve'"
            v-model="table.filters.status"
            :items="REQUEST_STATUS_OPTIONS"
            label="Estado"
            placeholder="Aprobado"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.priority"
            :items="PRIORITY_OPTIONS"
            label="Prioridad"
            placeholder="Urgente"
            density="compact"
            clearable
          />
        </template>

        <template #item.number="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.number }}</span>
        </template>

        <template #item.area="{ item }">
          {{ item.area?.name || '—' }}
        </template>

        <template #item.warehouse="{ item }">
          {{ item.warehouse ? `${item.warehouse.code} - ${item.warehouse.name}` : '—' }}
        </template>

        <template #item.requester="{ item }">
          {{ item.requester?.name || '—' }}
        </template>

        <template #item.required_date="{ item }">
          <span>{{ formatDateDMY(item.required_date) }}</span>
        </template>

        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="item.priority === 'urgent' ? 'error' : 'secondary'"
            :variant="item.priority === 'urgent' ? 'flat' : 'outlined'"
          >
            {{ item.priority_label }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            class="text-uppercase"
            :color="requestStatusColor(item.status)"
          >
            {{ requestStatusTitle(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              tooltip="Ver requerimiento"
              @click="openDetail(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>
  </div>
</template>
