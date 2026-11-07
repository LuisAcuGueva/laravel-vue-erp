<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { salesApi } from '@/modules/sales/services/salesApi'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  client: {
    type: Object,
    required: true,
  },
})

const router = useRouter()
const { can } = useAcl()
const notify = useNotify()

const sales = ref([])
const isLoading = ref(false)

const loadSales = async () => {
  if (!props.client?.id) return
  isLoading.value = true
  try {
    const res = await salesApi.list({
      client_id: props.client.id,
      per_page: 50,
    })

    sales.value = res?.data || []
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar las ventas del cliente')
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.client?.id, loadSales)
onMounted(loadSales)

const formatDate = val => {
  if (!val) return '—'
  const d = new Date(val)

  return Number.isNaN(d.getTime()) ? val : d.toLocaleString('es-PE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).replace(/\//g, '-')
}

const documentTypeBadge = type => {
  switch (type) {
  case 'invoice':
    return { text: 'Factura', color: 'primary' }
  case 'receipt':
    return { text: 'Boleta', color: 'info' }
  case 'sale_note':
    return { text: 'Nota de Venta', color: 'secondary' }
  default:
    return { text: type, color: 'default' }
  }
}

const statusBadge = status => {
  switch (status) {
  case 'completed':
    return { text: 'Completada', color: 'success' }
  case 'cancelled':
    return { text: 'Anulada', color: 'error' }
  default:
    return { text: status, color: 'default' }
  }
}

const handleDownloadPdf = async s => {
  try {
    await salesApi.downloadPdf(s.id, 'a4', `${s.full_number}.pdf`)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al descargar el comprobante en PDF')
  }
}
</script>

<template>
  <VCardItem>
    <template #title>
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-receipt"
          size="22"
        />
        <span>Ventas y comprobantes del cliente ({{ sales.length }})</span>
      </div>
    </template>

    <template #append>
      <VBtn
        v-if="can('sales.create')"
        size="small"
        prepend-icon="tabler-plus"
        @click="router.push(`/sales/pos?client_id=${client.id}`)"
      >
        Nueva venta (POS)
      </VBtn>
    </template>
  </VCardItem>

  <VDivider />

  <VCardText class="pa-0">
    <div
      v-if="isLoading"
      class="text-center py-6"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <VTable
      v-else-if="sales.length"
      hover
      density="comfortable"
    >
      <thead>
        <tr>
          <th style="min-width: 140px">
            COMPROBANTE
          </th>
          <th style="min-width: 130px">
            EMISIÓN
          </th>
          <th style="min-width: 110px">
            CONDICIÓN
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            TOTAL
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            PAGADO
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            SALDO
          </th>
          <th
            class="text-center"
            style="width: 110px"
          >
            ESTADO
          </th>
          <th
            class="text-center"
            style="width: 100px"
          >
            ACCIONES
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="s in sales"
          :key="s.id"
        >
          <td>
            <div class="d-flex align-center gap-2">
              <RouterLink
                :to="`/sales/history/${s.id}`"
                class="font-weight-bold font-mono text-primary text-decoration-none"
              >
                {{ s.full_number }}
              </RouterLink>
              <VChip
                size="x-small"
                :color="documentTypeBadge(s.document_type).color"
                variant="tonal"
              >
                {{ documentTypeBadge(s.document_type).text }}
              </VChip>
            </div>
          </td>

          <td class="font-mono text-caption text-no-wrap">
            {{ formatDate(s.issued_at) }}
          </td>

          <td class="text-capitalize text-body-2">
            {{ s.payment_condition === 'credit' ? 'Crédito' : 'Contado' }}
            <span
              v-if="s.payment_condition === 'credit' && s.credit_days"
              class="text-caption text-medium-emphasis"
            >
              ({{ s.credit_days }} d)
            </span>
          </td>

          <td class="text-right font-mono font-weight-bold">
            {{ formatAmount(s.total_amount, s.currency?.symbol || 'S/') }}
          </td>

          <td class="text-right font-mono text-success">
            {{ formatAmount(s.paid_amount, s.currency?.symbol || 'S/') }}
          </td>

          <td class="text-right font-mono">
            <span :class="Number(s.balance) > 0 ? 'text-warning font-weight-medium' : 'text-medium-emphasis'">
              {{ formatAmount(s.balance, s.currency?.symbol || 'S/') }}
            </span>
          </td>

          <td class="text-center">
            <VChip
              size="small"
              :color="statusBadge(s.status).color"
              variant="tonal"
            >
              {{ statusBadge(s.status).text }}
            </VChip>
          </td>

          <td class="text-center">
            <div class="d-flex align-center justify-center gap-1">
              <RowActionButton
                action="view"
                tooltip="Ver detalle"
                @click="router.push(`/sales/history/${s.id}`)"
              />

              <RowActionButton
                action="custom"
                icon="tabler-printer"
                tooltip="Descargar PDF"
                @click="handleDownloadPdf(s)"
              />
            </div>
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-else
      class="pa-8 text-center text-medium-emphasis"
    >
      <VIcon
        icon="tabler-receipt-off"
        size="36"
        class="mb-2"
      />
      <div class="text-body-1 font-weight-medium">
        Sin ventas registradas
      </div>
      <p class="text-caption mb-0">
        Este cliente aún no registra comprobantes ni ventas en el sistema.
      </p>
    </div>
  </VCardText>
</template>
