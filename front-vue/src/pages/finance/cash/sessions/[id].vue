<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import CashSummaryCards from '@/modules/finance/components/cash/CashSummaryCards.vue'
import CashCountDetailTable from '@/modules/finance/components/cash/CashCountDetailTable.vue'
import CashSessionMethodSummary from '@/modules/finance/components/cash/CashSessionMethodSummary.vue'
import CashReviewDialog from '@/modules/finance/components/cash/CashReviewDialog.vue'
import { cashSessionsApi } from '@/modules/finance/services/cashApi'
import { formatDateTime12h } from '@/utils/dates'

definePage({
  meta: {
    action: 'cash_sessions.view',
    title: 'Detalle de Sesión de Caja',
    icon: 'tabler-report-money',
    description: 'Detalle de sesión de caja, arqueo de fondos y movimientos.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation({ name: 'finance-cash-sessions' })

const sessionId = route.params.id
const sessionData = ref(null)
const isLoadingSession = ref(true)

const isReviewDialogOpen = ref(false)
const isSubmittingReview = ref(false)
const reviewDialogRef = ref(null)

const movements = ref([])
const totalMovements = ref(0)
const isLoadingMovements = ref(false)

const loadSession = async () => {
  isLoadingSession.value = true
  try {
    const res = await cashSessionsApi.get(sessionId)

    sessionData.value = res?.data || null
  }
  catch (err) {
    notify.error(err?.data?.message || 'Error al cargar detalle de sesión')
    router.push({ name: 'finance-cash-sessions' })
  }
  finally {
    isLoadingSession.value = false
  }
}

const fetchMovements = async params => {
  isLoadingMovements.value = true
  try {
    const res = await cashSessionsApi.movements(sessionId, params)

    movements.value = res?.data || []
    totalMovements.value = res?.meta?.total ?? movements.value.length

    return res
  }
  catch {
    movements.value = []
    totalMovements.value = 0

    return { data: [], meta: { total: 0 } }
  }
  finally {
    isLoadingMovements.value = false
  }
}

const table = useServerTable({
  fetch: fetchMovements,
})

onMounted(async () => {
  await loadSession()
})

const openReviewDialog = () => {
  isReviewDialogOpen.value = true
}

const handleReviewSubmit = async payload => {
  isSubmittingReview.value = true
  reviewDialogRef.value?.clearErrors()
  try {
    await cashSessionsApi.review(sessionId, payload)
    notify.success('Cierre de caja revisado correctamente')
    isReviewDialogOpen.value = false
    await loadSession()
  }
  catch (err) {
    reviewDialogRef.value?.setErrors(err)
  }
  finally {
    isSubmittingReview.value = false
  }
}

const formatCurrency = val => {
  if (val === null || val === undefined)
    return '—'
  const num = Number(val)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const headers = [
  { title: 'HORA', key: 'moved_at', sortable: false, alwaysVisible: true },
  { title: 'TIPO', key: 'direction', sortable: false, align: 'center' },
  { title: 'CONCEPTO', key: 'concept', sortable: false },
  { title: 'MÉTODO', key: 'payment_method', sortable: false },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'REFERENCIA', key: 'reference', sortable: false },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false },
  { title: 'REGISTRADO POR', key: 'creator', sortable: false },
]
</script>

<template>
  <div>
    <!-- Encabezado de Página -->
    <VCard class="mb-4">
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <!-- Botón Volver y Cabecera de Sesión -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
      <VBtn
        variant="tonal"
        color="secondary"
        @click="goBack"
      >
        <VIcon
          start
          icon="tabler-arrow-left"
        />
        Volver
      </VBtn>

      <div
        v-if="sessionData"
        class="d-flex align-center flex-wrap gap-2"
      >
        <VChip
          size="default"
          variant="outlined"
          color="primary"
        >
          <VIcon
            start
            icon="tabler-device-desktop"
          />
          {{ sessionData.box?.name }} ({{ sessionData.box?.code }})
        </VChip>

        <VChip
          size="default"
          variant="tonal"
          :color="sessionData.status === 'open' ? 'success' : 'secondary'"
        >
          {{ sessionData.status === 'open' ? 'Sesión Abierta' : 'Sesión Cerrada' }}
        </VChip>

        <VChip
          v-if="sessionData.review_status === 'pending'"
          size="default"
          color="warning"
          variant="tonal"
        >
          Revisión Pendiente
        </VChip>
        <VChip
          v-else-if="sessionData.review_status === 'approved'"
          size="default"
          color="success"
          variant="tonal"
        >
          Revisión Aprobada
        </VChip>
        <VChip
          v-else-if="sessionData.review_status === 'rejected'"
          size="default"
          color="error"
          variant="tonal"
        >
          Revisión Rechazada
        </VChip>

        <VBtn
          v-if="sessionData.review_status === 'pending' && can('cash_sessions.review')"
          color="warning"
          variant="elevated"
          @click="openReviewDialog"
        >
          <VIcon
            start
            icon="tabler-clipboard-check"
          />
          Revisar Arqueo
        </VBtn>
      </div>
    </div>

    <!-- Contenido del Detalle -->
    <div v-if="sessionData">
      <!-- Tarjetas de Resumen Financiero -->
      <CashSummaryCards
        :summary="sessionData.summary"
        :session="sessionData"
      />

      <!-- Datos de Apertura y Cierre -->
      <VRow class="mb-4">
        <!-- Apertura -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard border>
            <VCardItem class="border-b">
              <template #prepend>
                <VIcon
                  icon="tabler-door-enter"
                  color="primary"
                  class="me-2"
                />
              </template>
              <VCardTitle class="text-subtitle-1">
                Datos de Apertura
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4 text-body-2">
              <div class="d-flex justify-space-between mb-2">
                <span class="text-medium-emphasis">Fecha y hora:</span>
                <span class="font-weight-medium">{{ formatDateTime12h(sessionData.opened_at) }}</span>
              </div>
              <div class="d-flex justify-space-between mb-2">
                <span class="text-medium-emphasis">Abierto por:</span>
                <span class="font-weight-medium">{{ sessionData.opener?.name || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between mb-2">
                <span class="text-medium-emphasis">Fondo inicial:</span>
                <span class="font-weight-bold font-monospace">{{ formatCurrency(sessionData.opening_amount) }}</span>
              </div>
              <div
                v-if="sessionData.opening_notes"
                class="pt-2 border-t text-caption"
              >
                <strong>Observaciones:</strong> {{ sessionData.opening_notes }}
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Cierre -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard border>
            <VCardItem class="border-b">
              <template #prepend>
                <VIcon
                  icon="tabler-lock"
                  color="primary"
                  class="me-2"
                />
              </template>
              <VCardTitle class="text-subtitle-1">
                Datos de Cierre y Arqueo
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4 text-body-2">
              <div
                v-if="sessionData.closed_at"
                class="d-flex flex-column gap-2"
              >
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Fecha y hora de cierre:</span>
                  <span class="font-weight-medium">{{ formatDateTime12h(sessionData.closed_at) }}</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Cerrado por:</span>
                  <span class="font-weight-medium">{{ sessionData.closer?.name || '—' }}</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Efectivo esperado:</span>
                  <span class="font-weight-medium font-monospace">{{ formatCurrency(sessionData.expected_cash) }}</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Efectivo físico contado:</span>
                  <span class="font-weight-medium font-monospace">{{ formatCurrency(sessionData.counted_cash) }}</span>
                </div>
                <div class="d-flex justify-space-between font-weight-bold pt-1 border-t">
                  <span>Diferencia de arqueo:</span>
                  <span
                    class="font-monospace"
                    :class="Number(sessionData.difference) === 0 ? 'text-success' : (Number(sessionData.difference) > 0 ? 'text-info' : 'text-error')"
                  >
                    {{ Number(sessionData.difference) > 0 ? '+' : '' }}{{ formatCurrency(sessionData.difference) }}
                    ({{ Number(sessionData.difference) === 0 ? 'Cuadre exacto' : (Number(sessionData.difference) > 0 ? 'Sobrante' : 'Faltante') }})
                  </span>
                </div>
                <div
                  v-if="sessionData.closing_notes"
                  class="pt-2 border-t text-caption"
                >
                  <strong>Notas del cajero:</strong> {{ sessionData.closing_notes }}
                </div>
              </div>
              <div
                v-else
                class="text-disabled text-center py-6"
              >
                Esta sesión aún se encuentra abierta y en operación.
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Panel de Revisión si fue revisada -->
      <VCard
        v-if="sessionData.reviewed_at"
        border
        class="mb-4"
      >
        <VCardItem class="border-b">
          <template #prepend>
            <VIcon
              icon="tabler-clipboard-check"
              :color="sessionData.review_status === 'approved' ? 'success' : 'error'"
              class="me-2"
            />
          </template>
          <VCardTitle class="text-subtitle-1">
            Supervisión y Dictamen de Cierre
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4 text-body-2">
          <div class="d-flex justify-space-between mb-2">
            <span class="text-medium-emphasis">Dictamen:</span>
            <VChip
              size="small"
              :color="sessionData.review_status === 'approved' ? 'success' : 'error'"
              variant="tonal"
            >
              {{ sessionData.review_status === 'approved' ? 'Aprobado' : 'Rechazado' }}
            </VChip>
          </div>
          <div class="d-flex justify-space-between mb-2">
            <span class="text-medium-emphasis">Revisado por:</span>
            <span class="font-weight-medium">{{ sessionData.reviewer?.name || '—' }}</span>
          </div>
          <div class="d-flex justify-space-between mb-2">
            <span class="text-medium-emphasis">Fecha de revisión:</span>
            <span class="font-weight-medium">{{ formatDateTime12h(sessionData.reviewed_at) }}</span>
          </div>
          <div
            v-if="sessionData.review_notes"
            class="pt-2 border-t text-caption"
          >
            <strong>Observaciones de supervisión:</strong> {{ sessionData.review_notes }}
          </div>
        </VCardText>
      </VCard>

      <!-- Desglose Físico de Denominaciones (si existe) -->
      <div
        v-if="sessionData.count_details && sessionData.count_details.length > 0"
        class="mb-6"
      >
        <CashCountDetailTable
          :count-details="sessionData.count_details"
          :total-counted="Number(sessionData.counted_cash || 0)"
        />
      </div>

      <!-- Resumen por Método y Concepto -->
      <div class="mb-6">
        <CashSessionMethodSummary :summary="sessionData.summary" />
      </div>

      <!-- Movimientos de la Sesión -->
      <VCard border>
        <VCardItem class="border-b">
          <template #prepend>
            <VIcon
              icon="tabler-list"
              class="me-2 text-primary"
            />
          </template>
          <VCardTitle class="text-subtitle-1">
            Movimientos Registrados en el Turno
          </VCardTitle>
        </VCardItem>

        <ServerDataTable
          v-model:options="table.options"
          v-model:search="table.search"
          table-id="session-movements-detail"
          :headers="headers"
          :items="movements"
          :total-items="totalMovements"
          :loading="isLoadingMovements"
          :has-filters="false"
          search-placeholder="Buscar movimientos..."
          :creatable="false"
          @refresh="table.reload"
        >
          <!-- Hora -->
          <template #item.moved_at="{ item }">
            <span class="font-monospace text-caption">{{ formatDateTime12h(item.moved_at) }}</span>
          </template>

          <!-- Tipo -->
          <template #item.direction="{ item }">
            <VChip
              size="small"
              variant="tonal"
              :color="item.direction === 'in' ? 'success' : 'error'"
            >
              {{ item.direction === 'in' ? 'Ingreso' : 'Egreso' }}
            </VChip>
          </template>

          <!-- Concepto -->
          <template #item.concept="{ item }">
            <span class="font-weight-medium">{{ item.concept?.name || '—' }}</span>
          </template>

          <!-- Método -->
          <template #item.payment_method="{ item }">
            <span>{{ item.payment_method?.name || '—' }}</span>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <span
              class="font-monospace font-weight-bold"
              :class="item.direction === 'in' ? 'text-success' : 'text-error'"
            >
              {{ item.direction === 'in' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
            </span>
          </template>

          <!-- Referencia -->
          <template #item.reference="{ item }">
            <span>{{ item.reference || '—' }}</span>
          </template>

          <!-- Descripción -->
          <template #item.description="{ item }">
            <div>
              <div>{{ item.description || '—' }}</div>
              <div
                v-if="item.counterparty_name"
                class="text-caption text-disabled"
              >
                {{ item.counterparty_name }}
              </div>
            </div>
          </template>

          <!-- Registrado por -->
          <template #item.creator="{ item }">
            <span class="text-caption">{{ item.creator?.name || '—' }}</span>
          </template>
        </ServerDataTable>
      </VCard>
    </div>

    <!-- Modal de Revisión -->
    <CashReviewDialog
      ref="reviewDialogRef"
      v-model:is-dialog-open="isReviewDialogOpen"
      :session="sessionData"
      :is-submitting="isSubmittingReview"
      @submit="handleReviewSubmit"
    />
  </div>
</template>
