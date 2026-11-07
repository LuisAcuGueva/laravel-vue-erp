<script setup>
import { formatAmount } from '@/utils/documentTotals'

/**
 * Líneas de una OC en solo lectura, con el avance de recepción por línea y los totales calculados por el backend.
 */
const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
})

const symbol = () => props.order.currency?.symbol || ''
const decimals = () => props.order.currency?.decimals ?? 2

const label = item => (item.catalog_item
  ? `${item.catalog_item.code} · ${item.catalog_item.name}${item.presentation ? ` (${item.presentation.name})` : ''}`
  : item.description)

const received = item => {
  const quantity = Number(item.quantity) || 0

  return quantity > 0 ? Math.min(100, (Number(item.received_quantity) / quantity) * 100) : 0
}

const taxTitles = { taxable: 'Gravado', exempt: 'Exonerado', unaffected: 'Inafecto' }
</script>

<template>
  <div>
    <VTable density="comfortable">
      <thead>
        <tr>
          <th>#</th>
          <th>ARTÍCULO / DESCRIPCIÓN</th>
          <th class="text-end">
            CANTIDAD
          </th>
          <th class="text-end">
            P. UNIT.
          </th>
          <th class="text-end">
            DSCTO.
          </th>
          <th>IMPUESTO</th>
          <th class="text-end">
            IMPORTE
          </th>
          <th style="min-width: 150px;">
            RECIBIDO
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in props.order.items"
          :key="item.id"
        >
          <td>{{ item.line }}</td>
          <td>
            {{ label(item) }}
            <VChip
              v-if="item.request_number"
              size="x-small"
              color="info"
              variant="tonal"
              class="ms-1"
              :to="item.request_id ? { name: 'purchases-requests-id', params: { id: item.request_id } } : undefined"
            >
              {{ item.request_number }}
            </VChip>
            <VChip
              v-if="item.is_service"
              size="x-small"
              variant="outlined"
              class="ms-1"
            >
              Servicio
            </VChip>
          </td>
          <td class="text-end">
            {{ Number(item.quantity) }}
          </td>
          <td class="text-end">
            {{ formatAmount(item.unit_price, symbol(), decimals()) }}
          </td>
          <td class="text-end">
            {{ Number(item.discount_percent) }}%
          </td>
          <td>{{ taxTitles[item.tax_type] || item.tax_type }}</td>
          <td class="text-end font-weight-medium">
            {{ formatAmount(item.line_total, symbol(), decimals()) }}
          </td>
          <td>
            <div class="text-caption mb-1">
              {{ Number(item.received_quantity) }} de {{ Number(item.quantity) }}
            </div>
            <VProgressLinear
              :model-value="received(item)"
              :color="received(item) >= 100 ? 'success' : 'primary'"
              height="6"
              rounded
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div class="d-flex justify-end mt-6">
      <table style="min-width: 280px; border-collapse: separate; border-spacing: 0 6px;">
        <tbody>
          <tr v-if="Number(order.taxable_base) > 0">
            <td class="text-medium-emphasis">
              Op. gravada
            </td>
            <td class="text-end">
              {{ formatAmount(order.taxable_base, symbol(), decimals()) }}
            </td>
          </tr>
          <tr v-if="Number(order.exempt_base) > 0">
            <td class="text-medium-emphasis">
              Op. exonerada
            </td>
            <td class="text-end">
              {{ formatAmount(order.exempt_base, symbol(), decimals()) }}
            </td>
          </tr>
          <tr v-if="Number(order.unaffected_base) > 0">
            <td class="text-medium-emphasis">
              Op. inafecta
            </td>
            <td class="text-end">
              {{ formatAmount(order.unaffected_base, symbol(), decimals()) }}
            </td>
          </tr>
          <tr v-if="Number(order.discount_total) > 0">
            <td class="text-medium-emphasis">
              Descuentos
            </td>
            <td class="text-end">
              - {{ formatAmount(order.discount_total, symbol(), decimals()) }}
            </td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">
              IGV ({{ Number(order.tax_rate) }}%)
            </td>
            <td class="text-end">
              {{ formatAmount(order.tax_total, symbol(), decimals()) }}
            </td>
          </tr>
          <tr class="text-h6">
            <td>Total</td>
            <td class="text-end">
              {{ formatAmount(order.total, symbol(), decimals()) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
