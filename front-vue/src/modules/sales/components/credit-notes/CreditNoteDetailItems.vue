<script setup>
/* eslint-disable camelcase */
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
  currencyDecimals: {
    type: Number,
    default: 2,
  },
})
</script>

<template>
  <div class="credit-note-detail-items border rounded overflow-hidden">
    <VTable density="compact">
      <thead>
        <tr class="bg-light">
          <th style="inline-size: 50px;">
            #
          </th>
          <th style="min-inline-size: 200px;">
            DESCRIPCIÓN
          </th>
          <th
            style="inline-size: 110px;"
            class="text-right"
          >
            CANTIDAD
          </th>
          <th
            style="inline-size: 120px;"
            class="text-right"
          >
            PRECIO UNIT.
          </th>
          <th
            style="inline-size: 100px;"
            class="text-right"
          >
            DSCTO %
          </th>
          <th
            style="inline-size: 140px;"
            class="text-center"
          >
            RETORNO A STOCK
          </th>
          <th
            style="inline-size: 130px;"
            class="text-right font-weight-bold"
          >
            TOTAL
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in props.items"
          :key="item.id"
        >
          <td class="font-mono text-caption">
            {{ item.line }}
          </td>
          <td>
            <div class="font-weight-medium text-body-2">
              {{ item.description }}
            </div>
            <div
              v-if="item.tax_type"
              class="text-caption text-medium-emphasis"
            >
              Tipo afectación: {{ item.tax_type }}
            </div>
          </td>
          <td class="text-right font-mono text-body-2">
            {{ parseFloat(item.quantity || 0).toFixed(2) }}
          </td>
          <td class="text-right font-mono text-body-2">
            {{ props.currencySymbol }} {{ parseFloat(item.unit_price || 0).toFixed(props.currencyDecimals) }}
          </td>
          <td class="text-right font-mono text-body-2">
            {{ parseFloat(item.discount_percent || 0).toFixed(2) }}%
          </td>
          <td class="text-center">
            <VChip
              size="x-small"
              :color="item.return_to_stock ? 'success' : 'secondary'"
              variant="tonal"
            >
              {{ item.return_to_stock ? 'Sí reingresó' : 'No' }}
            </VChip>
          </td>
          <td class="text-right font-mono text-body-2 font-weight-bold">
            {{ props.currencySymbol }} {{ parseFloat(item.line_total || 0).toFixed(props.currencyDecimals) }}
          </td>
        </tr>

        <tr v-if="!props.items || props.items.length === 0">
          <td
            colspan="7"
            class="text-center py-4 text-medium-emphasis text-caption"
          >
            No hay ítems registrados en esta nota de crédito (descuento global o ajuste de valor).
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>
