<script setup>
import { computed } from 'vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useNotify } from '@/composables/useNotify'
import { usePosStore } from '@/modules/sales/stores/posStore'

const posStore = usePosStore()
const notify = useNotify()

const displayPrice = value => Number(value || 0).toFixed(2)

const currencySymbol = computed(() => posStore.currency?.symbol || 'S/')

const changeQuantity = (lineKey, value) => {
  const result = posStore.updateQuantity(lineKey, value)
  if (result?.ok === false)
    notify.error(result.message)
}
</script>

<template>
  <div class="pos-cart-table__scroll">
    <VTable
      hover
      density="comfortable"
    >
      <thead>
        <tr>
          <th style="min-width: 220px">
            PRODUCTO
          </th>
          <th
            class="text-center"
            style="width: 160px"
          >
            CANTIDAD
          </th>
          <th
            class="text-right"
            style="width: 130px"
          >
            PRECIO
          </th>
          <th
            class="text-right"
            style="width: 100px"
          >
            DSCTO %
          </th>
          <th
            class="text-right"
            style="width: 110px"
          >
            TOTAL
          </th>
          <th
            class="text-center"
            style="width: 60px"
          >
            ACCIÓN
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="line in posStore.cart"
          :key="line.key"
        >
          <td>
            <div class="font-weight-medium">
              {{ line.item_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ line.presentation_name }} · <span class="font-mono">{{ line.code }}</span>
            </div>
            <div
              v-if="line.track_stock && line.available_stock !== null"
              class="text-caption"
              :class="line.quantity >= line.available_stock ? 'text-warning' : 'text-medium-emphasis'"
            >
              Stock disponible: {{ line.available_stock }} {{ line.unit_symbol }}
            </div>
          </td>

          <td class="text-center">
            <div class="d-flex align-center justify-center gap-1">
              <VBtn
                size="x-small"
                icon="tabler-minus"
                variant="tonal"
                :disabled="line.quantity <= 1"
                @click="changeQuantity(line.key, line.quantity - 1)"
              />
              <VTextField
                :model-value="line.quantity"
                type="number"
                min="1"
                density="compact"
                hide-details
                class="text-right font-weight-bold no-spin-input"
                style="max-width: 72px"
                @update:model-value="val => changeQuantity(line.key, val)"
              />
              <VBtn
                size="x-small"
                icon="tabler-plus"
                variant="tonal"
                :disabled="line.track_stock && line.available_stock !== null && line.quantity >= line.available_stock"
                @click="changeQuantity(line.key, line.quantity + 1)"
              />
            </div>
          </td>

          <td class="text-right font-mono">
            <VTextField
              :model-value="displayPrice(line.unit_price)"
              type="number"
              step="0.01"
              density="compact"
              hide-details
              :prefix="currencySymbol"
              class="text-right no-spin-input"
              style="max-width: 115px; margin-left: auto"
              @update:model-value="val => posStore.updateUnitPrice(line.key, val)"
            />
          </td>

          <td class="text-right">
            <VTextField
              :model-value="line.discount_percent"
              type="number"
              min="0"
              max="100"
              density="compact"
              hide-details
              suffix="%"
              class="text-right no-spin-input"
              style="max-width: 85px; margin-left: auto"
              @update:model-value="val => posStore.updateDiscount(line.key, val)"
            />
          </td>

          <td class="text-right font-weight-bold font-mono">
            {{ currencySymbol }} {{ (Number(line.unit_price || 0) * Number(line.quantity || 1) * (1 - Number(line.discount_percent || 0)/100)).toFixed(2) }}
          </td>

          <td class="text-center">
            <RowActionButton
              action="delete"
              tooltip="Quitar producto"
              @click="posStore.removeLine(line.key)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style scoped>
.pos-cart-table__scroll {
  overflow-x: auto;
}

.no-spin-input :deep(input[type='number']::-webkit-outer-spin-button),
.no-spin-input :deep(input[type='number']::-webkit-inner-spin-button) {
  margin: 0;
  -webkit-appearance: none;
}

.no-spin-input :deep(input[type='number']) {
  -moz-appearance: textfield;
}

/* La clase utilitaria `text-right` de Vuetify alinea el campo, pero no siempre
   el texto tecleado dentro de <input>; se fuerza aquí para que cantidad,
   precio y descuento queden alineados a la derecha dentro del recuadro. */
.no-spin-input :deep(input) {
  text-align: right;
}
</style>
