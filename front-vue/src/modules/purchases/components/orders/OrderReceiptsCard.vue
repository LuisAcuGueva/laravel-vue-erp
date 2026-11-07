<script setup>
/* eslint-disable camelcase */
import { useRouter } from 'vue-router'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import {
  providerDocumentTypeTitle,
  receiptStatusColor,
  receiptStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
  canReceive: {
    type: Boolean,
    default: false,
  },
})

const router = useRouter()
</script>

<template>
  <VCard class="mt-6">
    <VCardItem>
      <template #title>
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-truck-loading"
            size="22"
          />
          <span>Recepciones asociadas ({{ props.order.receipts?.length || 0 }})</span>
        </div>
      </template>
      <template #append>
        <VBtn
          v-if="canReceive"
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="router.push({ name: 'purchases-receipts-new', query: { order_id: props.order.id } })"
        >
          Nueva recepción
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText class="pa-0">
      <VTable
        v-if="props.order.receipts && props.order.receipts.length > 0"
        density="compact"
      >
        <thead>
          <tr>
            <th>NÚMERO</th>
            <th>FECHA</th>
            <th>DOC. PROVEEDOR</th>
            <th>ALMACÉN</th>
            <th class="text-end">
              TOTAL COSTO
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
            v-for="rc in props.order.receipts"
            :key="rc.id"
          >
            <td>
              <RouterLink
                :to="{ name: 'purchases-receipts-id', params: { id: rc.id } }"
                class="font-weight-medium font-monospace text-primary text-decoration-none"
              >
                {{ rc.number }}
              </RouterLink>
            </td>
            <td>{{ formatDateDMY(rc.receipt_date) }}</td>
            <td>
              <div
                v-if="rc.supplier_invoices?.length"
                class="d-flex flex-wrap gap-4"
              >
                <RouterLink
                  v-for="invoice in rc.supplier_invoices"
                  :key="invoice.id"
                  :to="{ name: 'purchases-supplier-invoices-id', params: { id: invoice.id } }"
                  class="d-flex flex-column text-decoration-none"
                >
                  <span class="font-weight-medium font-monospace text-primary">{{ invoice.document_label }}</span>
                  <span class="text-caption text-medium-emphasis">{{ invoice.number }}</span>
                </RouterLink>
              </div>
              <div v-else>
                {{ providerDocumentTypeTitle(rc.provider_document_type) }}
                <span
                  v-if="rc.provider_document_number"
                  class="font-monospace ms-1"
                >
                  {{ rc.provider_document_number }}
                </span>
              </div>
            </td>
            <td>{{ rc.warehouse ? `${rc.warehouse.code} - ${rc.warehouse.name}` : '—' }}</td>
            <td class="text-end font-weight-medium">
              {{ formatAmount(rc.total_cost, 'S/') }}
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                class="text-uppercase"
                :color="receiptStatusColor(rc.status)"
              >
                {{ receiptStatusTitle(rc.status) }}
              </VChip>
            </td>
            <td class="text-center">
              <RowActionButton
                action="view"
                tooltip="Ver recepción"
                @click="router.push({ name: 'purchases-receipts-id', params: { id: rc.id } })"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
      <div
        v-else
        class="text-center text-medium-emphasis py-6"
      >
        No hay recepciones registradas para esta orden de compra.
      </div>
    </VCardText>
  </VCard>
</template>
