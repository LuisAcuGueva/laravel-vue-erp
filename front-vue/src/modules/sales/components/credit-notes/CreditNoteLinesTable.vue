<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'

const props = defineProps({
  lines: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: Array,
    required: true,
  },
  reasonCode: {
    type: String,
    required: true,
  },
  taxRate: {
    type: [Number, String],
    default: 18,
  },
  pricesIncludeTax: {
    type: Boolean,
    default: true,
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
  currencyDecimals: {
    type: Number,
    default: 2,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue'])

const isReturnReason = computed(() => ['01', '06', '07'].includes(props.reasonCode))
const isItemDiscountReason = computed(() => ['05', '09'].includes(props.reasonCode))
const isReadOnlyQuantities = computed(() => ['01', '06'].includes(props.reasonCode))

const getItemState = saleItemId => {
  return props.modelValue.find(it => it.sale_item_id === saleItemId) || null
}

const updateItem = (saleItemId, patch) => {
  const current = [...props.modelValue]
  const idx = current.findIndex(it => it.sale_item_id === saleItemId)

  if (idx >= 0) {
    current[idx] = { ...current[idx], ...patch }
  }
  else {
    current.push({
      sale_item_id: saleItemId,
      quantity: 0,
      amount: 0,
      return_to_stock: false,
      ...patch,
    })
  }

  emit('update:modelValue', current)
}

const toggleItemInclusion = (line, checked) => {
  const current = [...props.modelValue]
  const idx = current.findIndex(it => it.sale_item_id === line.id)

  if (checked) {
    if (idx < 0) {
      current.push({
        sale_item_id: line.id,
        quantity: parseFloat(line.pending_quantity) || 0,
        amount: 0,
        return_to_stock: Boolean(line.track_stock),
      })
    }
  }
  else {
    if (idx >= 0) {
      current.splice(idx, 1)
    }
  }

  emit('update:modelValue', current)
}

const calculateLineTotal = (line, itemState) => {
  if (!itemState) return 0

  if (isItemDiscountReason.value) {
    return parseFloat(itemState.amount) || 0
  }

  const qty = parseFloat(itemState.quantity) || 0
  const unitPrice = parseFloat(line.unit_price) || 0
  const discountPct = parseFloat(line.discount_percent) || 0
  const netUnitPrice = unitPrice * (1 - discountPct / 100)

  if (props.pricesIncludeTax) {
    return qty * netUnitPrice
  }

  const rate = parseFloat(props.taxRate) || 18
  const taxMultiplier = line.tax_type === 'taxable' ? (1 + rate / 100) : 1

  return qty * netUnitPrice * taxMultiplier
}
</script>

<template>
  <div class="credit-note-lines-table border rounded overflow-hidden">
    <VTable density="compact">
      <thead>
        <tr class="bg-light">
          <th
            v-if="props.reasonCode === '07'"
            style="inline-size: 50px;"
            class="text-center"
          >
            #
          </th>
          <th style="min-inline-size: 200px;">
            ÍTEM / DESCRIPCIÓN
          </th>
          <th
            style="inline-size: 110px;"
            class="text-right"
          >
            ORIGINAL
          </th>
          <th
            style="inline-size: 110px;"
            class="text-right"
          >
            PENDIENTE
          </th>

          <th
            v-if="isReturnReason"
            style="inline-size: 150px;"
            class="text-right"
          >
            CANT. A DEVOLVER *
          </th>

          <th
            v-if="isReturnReason"
            style="inline-size: 150px;"
            class="text-center"
          >
            REINGRESAR STOCK
          </th>

          <th
            v-if="isItemDiscountReason"
            style="inline-size: 160px;"
            class="text-right"
          >
            MONTO A REBAJAR *
          </th>

          <th
            style="inline-size: 130px;"
            class="text-right font-weight-bold"
          >
            TOTAL LÍNEA
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="line in props.lines"
          :key="line.id"
          :class="{ 'bg-var-theme-surface': !getItemState(line.id) && props.reasonCode === '07' }"
        >
          <!-- Selector checkbox para devolución parcial 07 -->
          <td
            v-if="props.reasonCode === '07'"
            class="text-center"
          >
            <VCheckbox
              :model-value="Boolean(getItemState(line.id))"
              density="compact"
              hide-details
              :disabled="parseFloat(line.pending_quantity) <= 0"
              @update:model-value="toggleItemInclusion(line, $event)"
            />
          </td>

          <!-- Descripción -->
          <td>
            <div class="font-weight-medium text-body-2">
              {{ line.description }}
            </div>
            <div class="text-caption text-medium-emphasis">
              P.U.: {{ props.currencySymbol }} {{ parseFloat(line.unit_price).toFixed(props.currencyDecimals) }}
              <span v-if="parseFloat(line.discount_percent) > 0"> (Desc: {{ line.discount_percent }}%)</span>
              · {{ line.track_stock ? 'Controla stock' : 'Sin stock' }}
            </div>
          </td>

          <!-- Cantidad original -->
          <td class="text-right font-mono text-body-2">
            {{ parseFloat(line.quantity).toFixed(2) }} {{ line.unit_symbol }}
          </td>

          <!-- Cantidad pendiente -->
          <td class="text-right font-mono text-body-2">
            <span :class="{ 'text-error': parseFloat(line.pending_quantity) <= 0 }">
              {{ parseFloat(line.pending_quantity).toFixed(2) }}
            </span>
          </td>

          <!-- Input Cantidad a devolver (si es motivo de devolución) -->
          <td
            v-if="isReturnReason"
            class="text-right"
          >
            <template v-if="getItemState(line.id)">
              <VTextField
                v-if="!isReadOnlyQuantities"
                :model-value="getItemState(line.id)?.quantity"
                type="number"
                step="0.01"
                min="0.01"
                :max="parseFloat(line.pending_quantity)"
                density="compact"
                hide-details="auto"
                label="Cant. *"
                placeholder="0.00"
                class="text-right font-mono"
                @update:model-value="val => updateItem(line.id, { quantity: parseFloat(val) || 0 })"
              />
              <span
                v-else
                class="font-mono text-body-2 font-weight-bold"
              >
                {{ parseFloat(getItemState(line.id)?.quantity || 0).toFixed(2) }}
              </span>
            </template>
            <span
              v-else
              class="text-medium-emphasis text-caption"
            >—</span>
          </td>

          <!-- Checkbox Reingresar Stock -->
          <td
            v-if="isReturnReason"
            class="text-center"
          >
            <template v-if="getItemState(line.id)">
              <VCheckbox
                :model-value="Boolean(getItemState(line.id)?.return_to_stock)"
                density="compact"
                hide-details
                :disabled="!line.track_stock || props.reasonCode === '01' || props.reasonCode === '06'"
                @update:model-value="val => updateItem(line.id, { return_to_stock: Boolean(val) })"
              />
            </template>
            <span
              v-else
              class="text-medium-emphasis text-caption"
            >—</span>
          </td>

          <!-- Input Monto a rebajar (si es motivo 05 u 09) -->
          <td
            v-if="isItemDiscountReason"
            class="text-right"
          >
            <VTextField
              :model-value="getItemState(line.id)?.amount"
              type="number"
              step="0.01"
              min="0.01"
              density="compact"
              hide-details="auto"
              label="Monto *"
              placeholder="0.00"
              class="text-right font-mono"
              @update:model-value="val => updateItem(line.id, { amount: parseFloat(val) || 0 })"
            />
          </td>

          <!-- Total de línea -->
          <td class="text-right font-mono text-body-2 font-weight-bold">
            {{ props.currencySymbol }} {{ calculateLineTotal(line, getItemState(line.id)).toFixed(props.currencyDecimals) }}
          </td>
        </tr>

        <tr v-if="!props.lines || props.lines.length === 0">
          <td
            colspan="8"
            class="text-center py-4 text-medium-emphasis text-caption"
          >
            No hay ítems disponibles para esta venta.
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
