<script setup>
import { computed } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  sale: {
    type: Object,
    required: true,
  },
})

const { can } = useAcl()
const canViewCosts = computed(() => can('sales.view_costs'))
const currencySymbol = computed(() => props.sale?.currency_symbol || 'S/')
const currencyDecimals = computed(() => props.sale?.currency_decimals ?? 2)
</script>

<template>
  <VCard title="Detalle de Ítems">
    <VTable
      hover
      density="comfortable"
    >
      <thead>
        <tr>
          <th style="width: 50px">
            #
          </th>
          <th style="min-width: 250px">
            DESCRIPCIÓN
          </th>
          <th
            class="text-center"
            style="width: 100px"
          >
            CANTIDAD
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            P. UNIT.
          </th>
          <th
            class="text-right"
            style="width: 90px"
          >
            DSCTO %
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            SUBTOTAL
          </th>
          <th
            class="text-right"
            style="width: 100px"
          >
            IGV
          </th>
          <th
            class="text-right"
            style="width: 120px"
          >
            TOTAL
          </th>
          <th
            v-if="canViewCosts"
            class="text-right text-primary"
            style="width: 110px"
          >
            COSTO
          </th>
          <th
            v-if="canViewCosts"
            class="text-right text-primary"
            style="width: 110px"
          >
            MARGEN
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in items"
          :key="item.id || item.line"
        >
          <td class="font-mono text-caption text-medium-emphasis">
            {{ item.line }}
          </td>

          <td>
            <div class="font-weight-medium">
              {{ item.description || item.item?.name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              <span
                v-if="item.item?.code"
                class="font-mono"
              >{{ item.item.code }} · </span>
              <span>{{ item.presentation?.name || 'Unidad' }}</span>
              <span
                v-if="item.tax_type"
                class="text-uppercase ms-1 text-primary"
              >({{ item.tax_type }})</span>
            </div>
          </td>

          <td class="text-center font-mono">
            {{ Number(item.quantity).toFixed(2) }}
          </td>

          <td class="text-right font-mono">
            {{ formatAmount(item.unit_price, currencySymbol, currencyDecimals) }}
          </td>

          <td class="text-right font-mono">
            {{ Number(item.discount_percent || 0) > 0 ? `${Number(item.discount_percent).toFixed(1)}%` : '—' }}
          </td>

          <td class="text-right font-mono">
            {{ formatAmount(item.line_subtotal, currencySymbol, currencyDecimals) }}
          </td>

          <td class="text-right font-mono">
            {{ formatAmount(item.line_tax, currencySymbol, currencyDecimals) }}
          </td>

          <td class="text-right font-mono font-weight-bold">
            {{ formatAmount(item.line_total, currencySymbol, currencyDecimals) }}
          </td>

          <td
            v-if="canViewCosts"
            class="text-right font-mono text-caption"
          >
            {{ item.cost_total != null ? formatAmount(item.cost_total, currencySymbol, currencyDecimals) : '—' }}
          </td>

          <td
            v-if="canViewCosts"
            class="text-right font-mono text-caption"
            :class="Number(item.margin) >= 0 ? 'text-success' : 'text-error'"
          >
            {{ item.margin != null ? formatAmount(item.margin, currencySymbol, currencyDecimals) : '—' }}
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Resumen de Bases y Totales -->
    <VDivider />
    <VCardText>
      <div class="d-flex justify-end">
        <div style="min-width: 280px; max-width: 380px">
          <div class="d-flex justify-space-between py-1 text-body-2">
            <span class="text-medium-emphasis">Op. Gravadas:</span>
            <span class="font-mono">{{ formatAmount(sale.taxable_base, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div
            v-if="Number(sale.exempt_base) > 0"
            class="d-flex justify-space-between py-1 text-body-2"
          >
            <span class="text-medium-emphasis">Op. Exoneradas:</span>
            <span class="font-mono">{{ formatAmount(sale.exempt_base, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div
            v-if="Number(sale.unaffected_base) > 0"
            class="d-flex justify-space-between py-1 text-body-2"
          >
            <span class="text-medium-emphasis">Op. Inafectas:</span>
            <span class="font-mono">{{ formatAmount(sale.unaffected_base, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div
            v-if="Number(sale.discount_total) > 0"
            class="d-flex justify-space-between py-1 text-body-2 text-warning"
          >
            <span>Descuento Total:</span>
            <span class="font-mono">- {{ formatAmount(sale.discount_total, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div class="d-flex justify-space-between py-1 text-body-2">
            <span class="text-medium-emphasis">IGV ({{ sale.tax_rate || 18 }}%):</span>
            <span class="font-mono">{{ formatAmount(sale.tax_total, currencySymbol, currencyDecimals) }}</span>
          </div>

          <VDivider class="my-2" />

          <div class="d-flex justify-space-between py-1 text-h6 font-weight-bold">
            <span>Total Venta:</span>
            <span class="font-mono text-primary">{{ formatAmount(sale.total, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div
            v-if="canViewCosts && sale.cost_total != null"
            class="d-flex justify-space-between py-1 text-caption text-medium-emphasis border-t mt-2 pt-2"
          >
            <span>Costo Total:</span>
            <span class="font-mono">{{ formatAmount(sale.cost_total, currencySymbol, currencyDecimals) }}</span>
          </div>

          <div
            v-if="canViewCosts && sale.margin_total != null"
            class="d-flex justify-space-between py-1 text-caption font-weight-medium"
            :class="Number(sale.margin_total) >= 0 ? 'text-success' : 'text-error'"
          >
            <span>Margen Global:</span>
            <span class="font-mono">{{ formatAmount(sale.margin_total, currencySymbol, currencyDecimals) }}</span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
