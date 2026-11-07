<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { quotationsApi } from '@/modules/sales/services/quotationsApi'
import {
  quotationStatusColor,
  quotationStatusTitle,
} from '@/modules/sales/utils/status'
import { formatDateDMY } from '@/utils/dates'
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

const quotations = ref([])
const isLoading = ref(false)

const loadQuotations = async () => {
  if (!props.client?.id) return
  isLoading.value = true
  try {
    const res = await quotationsApi.list({
      client_id: props.client.id,
      per_page: 50,
    })

    quotations.value = res?.data || []
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar las cotizaciones del cliente')
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.client?.id, loadQuotations)
onMounted(loadQuotations)

const handleDownloadPdf = async q => {
  try {
    await quotationsApi.downloadPdf(q.id, q.number)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al descargar el PDF')
  }
}
</script>

<template>
  <VCardItem>
    <template #title>
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-file-dollar"
          size="22"
        />
        <span>Cotizaciones del cliente ({{ quotations.length }})</span>
      </div>
    </template>

    <template #append>
      <VBtn
        v-if="can('quotations.create')"
        size="small"
        prepend-icon="tabler-plus"
        @click="router.push({ name: 'sales-quotations-new', query: { client_id: client.id } })"
      >
        Nueva cotización
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
      v-else-if="quotations.length"
      density="compact"
    >
      <thead>
        <tr>
          <th>NÚMERO</th>
          <th>EMISIÓN</th>
          <th>VÁLIDA HASTA</th>
          <th>VENDEDOR</th>
          <th class="text-end">
            TOTAL
          </th>
          <th class="text-center">
            ESTADO
          </th>
          <th class="text-center">
            ACCIONES
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="q in quotations"
          :key="q.id"
        >
          <td>
            <RouterLink
              :to="{ name: 'sales-quotations-id', params: { id: q.id } }"
              class="font-weight-medium font-monospace text-primary text-decoration-none"
            >
              {{ q.number }}
            </RouterLink>
          </td>
          <td>{{ formatDateDMY(q.issue_date) }}</td>
          <td>
            <div class="d-flex align-center gap-1">
              <span>{{ formatDateDMY(q.valid_until) }}</span>
              <VChip
                v-if="q.is_expired && ['draft', 'sent'].includes(q.status)"
                size="x-small"
                color="error"
                variant="tonal"
              >
                Vencida
              </VChip>
            </div>
          </td>
          <td>{{ q.seller?.full_name || q.seller?.name || '—' }}</td>
          <td class="text-end font-weight-medium">
            {{ formatAmount(q.total, q.currency?.symbol) }}
          </td>
          <td class="text-center">
            <VChip
              size="x-small"
              class="text-uppercase"
              :color="quotationStatusColor(q.status)"
            >
              {{ quotationStatusTitle(q.status) }}
            </VChip>
          </td>
          <td class="text-center">
            <div class="d-flex justify-center gap-1">
              <RowActionButton
                action="view"
                tooltip="Ver cotización"
                @click="router.push({ name: 'sales-quotations-id', params: { id: q.id } })"
              />
              <RowActionButton
                action="custom"
                icon="tabler-file-download"
                tooltip="Descargar PDF"
                @click="handleDownloadPdf(q)"
              />
            </div>
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-else
      class="text-center text-medium-emphasis py-8"
    >
      Este cliente aún no tiene cotizaciones emitidas.
    </div>
  </VCardText>
</template>
