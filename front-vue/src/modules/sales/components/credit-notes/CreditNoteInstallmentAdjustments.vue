<script setup>
/* eslint-disable camelcase */
const props = defineProps({
  adjustments: {
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
  <div class="credit-note-installment-adjustments border rounded overflow-hidden">
    <VTable density="compact">
      <thead>
        <tr class="bg-light">
          <th style="inline-size: 150px;">
            CUOTA AFECTADA
          </th>
          <th class="text-right">
            MONTO DEDUCIDO
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="adj in props.adjustments"
          :key="adj.id"
        >
          <td class="font-mono text-body-2">
            Cuota #{{ adj.installment_number }}
          </td>
          <td class="text-right font-mono font-weight-bold text-warning">
            - {{ props.currencySymbol }} {{ parseFloat(adj.amount).toFixed(props.currencyDecimals) }}
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style scoped>
.font-mono {
  font-family: monospace;
}
</style>
