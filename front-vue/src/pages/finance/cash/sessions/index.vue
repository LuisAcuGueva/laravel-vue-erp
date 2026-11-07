<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CashReviewDialog from '@/modules/finance/components/cash/CashReviewDialog.vue'
import { cashSessionsApi } from '@/modules/finance/services/cashApi'
import { formatDateTime12h } from '@/utils/dates'

definePage({
  meta: {
    action: 'cash_sessions.view',
    title: 'Historial de Caja',
    icon: 'tabler-report-money',
    description: 'Historial de sesiones de caja, arqueos y bandeja de revisiones.',
  },
})

const { can } = useAcl()
const notify = useNotify()
const router = useRouter()

const items = ref([])
const totalItems = ref(0)
const isLoading = ref(false)

const isReviewDialogOpen = ref(false)
const selectedSessionToReview = ref(null)
const isSubmittingReview = ref(false)
const reviewDialogRef = ref(null)

const fetchSessions = async params => {
  isLoading.value = true
  try {
    const res = await cashSessionsApi.list(params)

    items.value = res?.data || []
    totalItems.value = res?.meta?.total ?? items.value.length

    return res
  }
  finally {
    isLoading.value = false
  }
}

const table = useServerTable({
  fetch: fetchSessions,
  filters: {
    status: null,
    'review_status': null,
  },
})

const headers = [
  { title: 'N° SESIÓN', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'CAJA', key: 'box', sortable: false, alwaysVisible: true },
  { title: 'APERTURA', key: 'opened_at', sortable: false },
  { title: 'CIERRE', key: 'closed_at', sortable: false },
  { title: 'F. APERTURA', key: 'opening_amount', sortable: false, align: 'end' },
  { title: 'ESPERADO', key: 'expected_cash', sortable: false, align: 'end' },
  { title: 'CONTADO', key: 'counted_cash', sortable: false, align: 'end' },
  { title: 'DIFERENCIA', key: 'difference', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'REVISIÓN', key: 'review_status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const STATUS_FILTER_OPTIONS = [
  { title: 'Abierta', value: 'open' },
  { title: 'Cerrada', value: 'closed' },
]

const REVIEW_FILTER_OPTIONS = [
  { title: 'Pendiente', value: 'pending' },
  { title: 'Aprobado', value: 'approved' },
  { title: 'Rechazado', value: 'rejected' },
]

const formatCurrency = val => {
  if (val === null || val === undefined)
    return '—'
  const num = Number(val)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const viewDetails = session => {
  router.push({ name: 'finance-cash-sessions-id', params: { id: session.id } })
}

const openReview = session => {
  selectedSessionToReview.value = session
  isReviewDialogOpen.value = true
}

const handleReviewSubmit = async payload => {
  if (!selectedSessionToReview.value)
    return
  isSubmittingReview.value = true
  reviewDialogRef.value?.clearErrors()
  try {
    await cashSessionsApi.review(selectedSessionToReview.value.id, payload)
    notify.success('Cierre de caja revisado correctamente')
    isReviewDialogOpen.value = false
    await table.reload()
  }
  catch (err) {
    reviewDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingReview.value = false
  }
}
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
        table-id="cash-sessions"
        :headers="headers"
        :items="items"
        :total-items="totalItems"
        :loading="isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por número o caja..."
        :creatable="false"
        @refresh="table.reload"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.status"
            :items="STATUS_FILTER_OPTIONS"
            label="Estado Sesión"
            placeholder="Abierta o Cerrada"
            density="compact"
            clearable
          />

          <VSelect
            v-model="table.filters.review_status"
            :items="REVIEW_FILTER_OPTIONS"
            label="Estado Revisión"
            placeholder="Pendiente o Aprobado"
            density="compact"
            clearable
          />
        </template>

        <!-- Número -->
        <template #item.number="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.number }}</span>
        </template>

        <!-- Caja -->
        <template #item.box="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.box?.name || '—' }}
            </div>
            <div
              v-if="item.box?.code"
              class="text-caption text-disabled"
            >
              {{ item.box.code }}
            </div>
          </div>
        </template>

        <!-- Apertura -->
        <template #item.opened_at="{ item }">
          <div>
            <div>{{ formatDateTime12h(item.opened_at) }}</div>
            <div
              v-if="item.opener"
              class="text-caption text-disabled"
            >
              {{ item.opener.name }}
            </div>
          </div>
        </template>

        <!-- Cierre -->
        <template #item.closed_at="{ item }">
          <div v-if="item.closed_at">
            <div>{{ formatDateTime12h(item.closed_at) }}</div>
            <div
              v-if="item.closer"
              class="text-caption text-disabled"
            >
              {{ item.closer.name }}
            </div>
          </div>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Monto apertura -->
        <template #item.opening_amount="{ item }">
          <span class="font-monospace">{{ formatCurrency(item.opening_amount) }}</span>
        </template>

        <!-- Esperado -->
        <template #item.expected_cash="{ item }">
          <span class="font-monospace">{{ formatCurrency(item.expected_cash) }}</span>
        </template>

        <!-- Contado -->
        <template #item.counted_cash="{ item }">
          <span class="font-monospace">{{ formatCurrency(item.counted_cash) }}</span>
        </template>

        <!-- Diferencia -->
        <template #item.difference="{ item }">
          <div v-if="item.difference !== null">
            <VChip
              size="small"
              variant="tonal"
              :color="Number(item.difference) === 0 ? 'success' : (Number(item.difference) > 0 ? 'info' : 'error')"
            >
              {{ Number(item.difference) > 0 ? '+' : '' }}{{ formatCurrency(item.difference) }}
            </VChip>
          </div>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Estado -->
        <template #item.status="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="item.status === 'open' ? 'success' : 'secondary'"
          >
            {{ item.status === 'open' ? 'Abierta' : 'Cerrada' }}
          </VChip>
        </template>

        <!-- Revisión -->
        <template #item.review_status="{ item }">
          <VChip
            v-if="item.review_status === 'pending'"
            size="small"
            color="warning"
            variant="tonal"
          >
            Pendiente
          </VChip>
          <VChip
            v-else-if="item.review_status === 'approved'"
            size="small"
            color="success"
            variant="tonal"
          >
            Aprobado
          </VChip>
          <VChip
            v-else-if="item.review_status === 'rejected'"
            size="small"
            color="error"
            variant="tonal"
          >
            Rechazado
          </VChip>
          <span
            v-else
            class="text-disabled text-caption"
          >—</span>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              @click="viewDetails(item)"
            />
            <RowActionButton
              v-if="item.review_status === 'pending' && can('cash_sessions.review')"
              action="custom"
              icon="tabler-clipboard-check"
              color="warning"
              tooltip="Revisar arqueo de cierre"
              @click="openReview(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <CashReviewDialog
      ref="reviewDialogRef"
      v-model:is-dialog-open="isReviewDialogOpen"
      :session="selectedSessionToReview"
      :is-submitting="isSubmittingReview"
      @submit="handleReviewSubmit"
    />
  </div>
</template>
