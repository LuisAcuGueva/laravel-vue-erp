<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ClientPicker from '@/components/common/ClientPicker.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { employeesApi } from '@/modules/maintenance/services/employeesApi'
import { quotationsApi } from '@/modules/sales/services/quotationsApi'
import { useQuotationsStore } from '@/modules/sales/stores/quotationsStore'
import {
  QUOTATION_STATUS_OPTIONS,
  quotationStatusColor,
  quotationStatusIcon,
  quotationStatusTitle,
} from '@/modules/sales/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'quotations.view',
    title: 'Cotizaciones',
    icon: 'tabler-file-dollar',
    description: 'Gestión de cotizaciones comerciales, emisión, seguimiento de vigencia y conversión',
  },
})

const router = useRouter()
const store = useQuotationsStore()
const { can } = useAcl()
const notify = useNotify()

const sellers = ref([])
const isDuplicating = ref(false)

const sellerOptions = computed(() =>
  sellers.value.map(s => ({
    title: s.full_name,
    value: s.id,
  })),
)

const STATUS_SUMMARY_CARDS = [
  { status: 'draft', title: 'Borradores' },
  { status: 'sent', title: 'Enviadas (Vigentes)' },
  { status: 'accepted', title: 'Aceptadas' },
  { status: 'converted', title: 'Convertidas' },
  { status: 'expired', title: 'Vencidas' },
]

const table = useServerTable({
  fetch: params => store.fetchQuotations(params),
  filters: {
    status: null,
    client_id: null,
    seller_id: null,
    date_from: null,
    date_to: null,
  },
})

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'EMISIÓN', key: 'issue_date', sortable: false },
  { title: 'CLIENTE', key: 'client', sortable: false },
  { title: 'VENDEDOR', key: 'seller', sortable: false },
  { title: 'VALIDEZ', key: 'valid_until', sortable: false },
  { title: 'TOTAL', key: 'total', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

onMounted(async () => {
  try {
    const res = await employeesApi.options()

    sellers.value = res?.data || []
  }
  catch {
    // Silencioso: si falla, el filtro de vendedor queda vacío
  }
})

const openDetail = quotation => {
  router.push({ name: 'sales-quotations-id', params: { id: quotation.id } })
}

const handleDuplicate = async quotation => {
  if (isDuplicating.value) return
  isDuplicating.value = true
  try {
    const res = await quotationsApi.duplicate(quotation.id)

    notify.success(`Cotización ${res?.data?.number || ''} creada a partir de ${quotation.number}`)
    if (res?.meta?.skipped_lines?.length) {
      notify.warning(`Se omitieron las líneas ${res.meta.skipped_lines.join(', ')} por contener ítems inactivos o no vendibles`)
    }
    await table.reload()
    if (res?.data?.id) {
      router.push({ name: 'sales-quotations-id', params: { id: res.data.id } })
    }
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al duplicar cotización')
  }
  finally {
    isDuplicating.value = false
  }
}

const handleDownloadPdf = async quotation => {
  try {
    await quotationsApi.downloadPdf(quotation.id, quotation.number)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al descargar el PDF de la cotización')
  }
}

const filterByStatusCard = status => {
  if (table.filters.status === status) {
    table.filters.status = null
  }
  else {
    table.filters.status = status
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <!-- Tarjetas de resumen por estado -->
    <div class="status-summary-grid mb-6">
      <VCard
        v-for="card in STATUS_SUMMARY_CARDS"
        :key="card.status"
        link
        :class="{ 'status-summary-card-active': table.filters.status === card.status }"
        :style="table.filters.status === card.status ? { borderColor: `rgb(var(--v-theme-${quotationStatusColor(card.status)}))` } : undefined"
        :variant="table.filters.status === card.status ? 'tonal' : undefined"
        :color="table.filters.status === card.status ? quotationStatusColor(card.status) : undefined"
        @click="filterByStatusCard(card.status)"
      >
        <VCardText class="d-flex align-center justify-space-between py-4">
          <div>
            <div class="text-caption text-medium-emphasis text-uppercase font-weight-medium">
              {{ card.title }}
            </div>
            <div class="text-h5 font-weight-bold mt-1">
              {{ store.totalsByStatus[card.status]?.count || 0 }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ formatAmount(store.totalsByStatus[card.status]?.total || 0, 'S/') }}
            </div>
          </div>
          <VAvatar
            :color="quotationStatusColor(card.status)"
            variant="tonal"
            size="42"
          >
            <VIcon
              :icon="quotationStatusIcon(card.status)"
              size="24"
            />
          </VAvatar>
        </VCardText>
      </VCard>
    </div>

    <VCard>
      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="sales-quotations"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="COT-2026-000001 o Los Andes"
        create-label="Nueva cotización"
        :creatable="can('quotations.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'sales-quotations-new' })"
      >
        <template #filters>
          <ClientPicker
            v-model="table.filters.client_id"
            label="Cliente"
            placeholder="Escribe nombre o documento, p. ej. 20601234567"
            density="compact"
            :creatable="false"
            clearable
            class="sales-filter-client"
          />

          <div class="sales-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
            <VSelect
              v-model="table.filters.status"
              :items="QUOTATION_STATUS_OPTIONS"
              label="Estado"
              placeholder="Seleccione estado"
              density="compact"
              clearable
            />
            <VAutocomplete
              v-model="table.filters.seller_id"
              :items="sellerOptions"
              label="Vendedor"
              placeholder="Seleccione vendedor"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_from"
              label="Desde"
              placeholder="YYYY-MM-DD"
              type="date"
              density="compact"
              clearable
            />
            <VTextField
              v-model="table.filters.date_to"
              label="Hasta"
              placeholder="YYYY-MM-DD"
              type="date"
              density="compact"
              clearable
            />
          </div>
        </template>

        <template #item.number="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.number }}</span>
        </template>

        <template #item.issue_date="{ item }">
          <span>{{ formatDateDMY(item.issue_date) }}</span>
        </template>

        <template #item.client="{ item }">
          <div class="d-flex flex-column py-1">
            <RouterLink
              v-if="item.client?.id"
              :to="`/clients/${item.client.id}`"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ item.client?.name }}
            </RouterLink>
            <span
              v-else
              class="font-weight-medium text-high-emphasis"
            >{{ item.client?.name }}</span>
            <span
              v-if="item.client?.document_number"
              class="text-caption text-medium-emphasis"
            >
              {{ item.client?.document_type }} {{ item.client?.document_number }}
            </span>
          </div>
        </template>

        <template #item.seller="{ item }">
          <span>{{ item.seller?.full_name || item.seller?.name || '—' }}</span>
        </template>

        <template #item.valid_until="{ item }">
          <div class="d-flex align-center gap-1">
            <span>{{ formatDateDMY(item.valid_until) }}</span>
            <VChip
              v-if="item.is_expired && ['draft', 'sent'].includes(item.status)"
              size="x-small"
              color="error"
              variant="tonal"
            >
              Vencida
            </VChip>
          </div>
        </template>

        <template #item.total="{ item }">
          <span class="font-weight-medium">{{ formatAmount(item.total, item.currency?.symbol) }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            class="text-uppercase"
            :color="quotationStatusColor(item.status)"
          >
            {{ quotationStatusTitle(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <RowActionButton
              action="view"
              tooltip="Ver detalle"
              @click="openDetail(item)"
            />
            <RowActionButton
              v-if="can('quotations.create')"
              action="custom"
              icon="tabler-copy"
              tooltip="Duplicar cotización"
              @click="handleDuplicate(item)"
            />
            <RowActionButton
              action="custom"
              icon="tabler-file-download"
              tooltip="Descargar PDF"
              @click="handleDownloadPdf(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.status-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 24px;
}

.status-summary-card-active {
  border: 1px solid;
}

.sales-filter-client {
  flex: 2 1 20rem;
  min-inline-size: 16rem;
  max-inline-size: 34rem;

  :deep(.v-input) {
    inline-size: 100%;
  }
}

.sales-filters-row-2 {
  flex-basis: 100%;

  :deep(.v-input) {
    flex: 1 1 9rem;
    min-inline-size: 8rem;
    max-inline-size: 14rem;
  }
}

@media (max-width: 599.98px) {
  .sales-filter-client {
    max-inline-size: none;
    inline-size: 100%;
  }

  .sales-filters-row-2 {
    flex-direction: column;
    align-items: stretch !important;

    :deep(.v-input) {
      max-inline-size: none;
      inline-size: 100%;
    }
  }
}
</style>
