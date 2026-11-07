<script setup>
import { computed } from 'vue'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  readonly: {
    type: Boolean,
    default: false,
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

const emit = defineEmits(['update:items', 'itemChange'])

const itemTitle = item => {
  if (item.catalog_item) {
    const code = item.catalog_item.code ? `${item.catalog_item.code} · ` : ''
    const name = item.catalog_item.name || ''
    const pres = item.presentation?.name ? ` (${item.presentation.name})` : ''

    return `${code}${name}${pres}`
  }

  return item.description || item.order_item?.description || '—'
}

const receiveAll = () => {
  props.items.forEach(item => {
    item.quantity = Number(item.pending_quantity || 0)
  })
}

const clearAll = () => {
  props.items.forEach(item => {
    item.quantity = 0
  })
}

const lineTotal = item => {
  const qty = Number(item.quantity) || 0
  const cost = Number(props.readonly ? item.unit_cost : (item.unit_cost_preview || item.unit_cost || 0))

  return qty * cost
}

const totalEstimated = computed(() => {
  return props.items.reduce((acc, item) => acc + lineTotal(item), 0)
})

const totalUnits = computed(() => {
  return props.items.reduce((acc, item) => acc + (Number(item.quantity) || 0), 0)
})
</script>

<template>
  <div>
    <div
      v-if="!readonly"
      class="d-flex justify-space-between align-center mb-3"
    >
      <div class="text-subtitle-2 text-medium-emphasis">
        Líneas a recibir ({{ items.length }})
      </div>
      <div class="d-flex gap-2">
        <VBtn
          size="small"
          variant="tonal"
          prepend-icon="tabler-checks"
          @click="receiveAll"
        >
          Recibir todo el saldo
        </VBtn>
        <VBtn
          size="small"
          variant="text"
          color="secondary"
          prepend-icon="tabler-clear-all"
          @click="clearAll"
        >
          Limpiar cantidades
        </VBtn>
      </div>
    </div>

    <VTable density="comfortable">
      <thead>
        <tr>
          <th style="width: 48px;">
            #
          </th>
          <th>ARTÍCULO / DESCRIPCIÓN</th>
          <th
            v-if="!readonly"
            class="text-end"
            style="width: 100px;"
          >
            ORDENADO
          </th>
          <th
            v-if="!readonly"
            class="text-end"
            style="width: 100px;"
          >
            RECIBIDO
          </th>
          <th
            v-if="!readonly"
            class="text-end"
            style="width: 100px;"
          >
            PENDIENTE
          </th>
          <th
            :class="readonly ? 'text-end' : ''"
            :style="readonly ? 'width: 120px;' : 'width: 140px;'"
          >
            {{ readonly ? 'CANTIDAD' : 'A RECIBIR *' }}
          </th>
          <th
            class="text-end"
            style="width: 130px;"
          >
            COSTO UNIT.
          </th>
          <th
            class="text-end"
            style="width: 130px;"
          >
            SUBTOTAL
          </th>
          <th :style="readonly ? 'width: 180px;' : 'width: 220px;'">
            OBSERVACIONES
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(item, idx) in items"
          :key="item.purchase_order_item_id || item.id || idx"
        >
          <td>{{ item.line || idx + 1 }}</td>
          <td>
            <div class="font-weight-medium">
              {{ itemTitle(item) }}
            </div>
            <div class="d-flex align-center gap-1 mt-1">
              <VChip
                v-if="item.is_service"
                size="x-small"
                color="info"
                variant="outlined"
              >
                Servicio
              </VChip>
              <span
                v-if="item.presentation?.name"
                class="text-caption text-medium-emphasis"
              >
                {{ item.presentation.name }}
              </span>
            </div>
          </td>

          <!-- Columnas de saldos en modo edición -->
          <td
            v-if="!readonly"
            class="text-end"
          >
            {{ Number(item.ordered_quantity || 0) }}
          </td>
          <td
            v-if="!readonly"
            class="text-end"
          >
            {{ Number(item.received_quantity || 0) }}
          </td>
          <td
            v-if="!readonly"
            class="text-end font-weight-medium"
          >
            {{ Number(item.pending_quantity || 0) }}
          </td>

          <!-- Cantidad a recibir -->
          <td :class="readonly ? 'text-end font-weight-medium' : ''">
            <template v-if="readonly">
              {{ Number(item.quantity || 0) }}
            </template>
            <template v-else>
              <VTextField
                v-model.number="item.quantity"
                type="number"
                min="0"
                step="any"
                density="compact"
                placeholder="0"
                hide-details
                :rules="[v => v >= 0 || 'Inválido']"
              />
            </template>
          </td>

          <!-- Costo unitario -->
          <td class="text-end">
            {{ formatAmount(readonly ? item.unit_cost : (item.unit_cost_preview || item.unit_cost || 0), currencySymbol, currencyDecimals) }}
          </td>

          <!-- Subtotal costo -->
          <td class="text-end font-weight-medium">
            {{ formatAmount(lineTotal(item), currencySymbol, currencyDecimals) }}
          </td>

          <!-- Observaciones de la línea -->
          <td>
            <template v-if="readonly">
              <span class="text-caption text-medium-emphasis">{{ item.notes || '—' }}</span>
            </template>
            <template v-else>
              <VTextField
                v-model="item.notes"
                placeholder="Ej. Bulto 1 de 2"
                density="compact"
                hide-details
              />
            </template>
          </td>
        </tr>

        <tr v-if="items.length === 0">
          <td
            :colspan="readonly ? 6 : 9"
            class="text-center text-medium-emphasis py-4"
          >
            No hay líneas para mostrar
          </td>
        </tr>
      </tbody>
      <tfoot v-if="items.length > 0">
        <tr>
          <td
            :colspan="readonly ? 2 : 5"
            class="text-end font-weight-bold"
          >
            Total:
          </td>
          <td :class="readonly ? 'text-end font-weight-bold' : ''">
            <span class="font-weight-bold">{{ totalUnits }}</span>
          </td>
          <td />
          <td class="text-end font-weight-bold">
            {{ formatAmount(totalEstimated, currencySymbol, currencyDecimals) }}
          </td>
          <td />
        </tr>
      </tfoot>
    </VTable>
  </div>
</template>
