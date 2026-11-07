<script setup>
/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  noteType: {
    type: String,
    default: 'in',
    validator: v => ['in', 'out', 'transfer'].includes(v),
  },
  warehouseId: {
    type: String,
    default: null,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue'])

const pickerKey = ref(0)
const pickerSelectedId = ref(null)

const lines = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const formatCurrency = val => {
  const num = Number(val || 0)
  
  return num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const handleItemSelect = item => {
  if (!item) return

  // Comprobar si ya está en la lista la misma presentación
  const existingIndex = lines.value.findIndex(l => l.item_presentation_id === item.presentation_id)
  if (existingIndex >= 0) {
    // Si ya existe, incrementamos cantidad
    const updated = [...lines.value]

    updated[existingIndex].quantity = Number(updated[existingIndex].quantity || 0) + 1
    updated[existingIndex].total_cost = calculateSubtotal(updated[existingIndex])
    lines.value = updated
  } else {
    const cost = props.noteType === 'in' ? Number(item.last_purchase_cost || 0) : Number(item.avg_cost || 0)

    const newLine = {
      catalog_item_id: item.catalog_item_id,
      item_presentation_id: item.presentation_id,
      code: item.code,
      name: item.name,
      presentation_name: item.presentation_name || item.unit_name || 'Unidad',
      unit_symbol: item.unit_symbol || 'UND',
      allow_decimals: Boolean(item.allow_decimals),
      factor: Number(item.factor || 1),
      stock_available: item.stock_available !== undefined ? Number(item.stock_available) : null,
      quantity: 1,
      unit_cost: cost,
      total_cost: cost * 1,
      notes: '',
    }

    lines.value = [...lines.value, newLine]
  }

  // Reset picker
  pickerSelectedId.value = null
  pickerKey.value++
}

const calculateSubtotal = line => {
  const q = Number(line.quantity || 0)
  const c = Number(line.unit_cost || 0)
  
  return Number((q * c).toFixed(4))
}

const updateLineSubtotal = (line, index) => {
  const updated = [...lines.value]

  updated[index].total_cost = calculateSubtotal(line)
  lines.value = updated
}

const removeLine = index => {
  const updated = [...lines.value]

  updated.splice(index, 1)
  lines.value = updated
}

const grandTotal = computed(() => {
  return lines.value.reduce((acc, curr) => acc + Number(curr.total_cost || 0), 0)
})

const totalUnits = computed(() => {
  return lines.value.reduce((acc, curr) => acc + Number(curr.quantity || 0), 0)
})
</script>

<template>
  <div class="warehouse-note-items-table">
    <!-- Selector de artículo (solo en modo edición) -->
    <div
      v-if="!readonly"
      class="mb-4"
    >
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <CatalogItemPicker
            :key="pickerKey"
            v-model="pickerSelectedId"
            context="stock"
            :warehouse-id="warehouseId"
            label="Agregar artículo"
            placeholder="Laptop o ITM-000001"
            :disabled="!warehouseId"
            @selected="handleItemSelect"
          />
        </VCol>
        <VCol
          v-if="!warehouseId"
          cols="12"
          md="4"
          class="d-flex align-center"
        >
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-0"
          >
            Selecciona primero el almacén de origen
          </VAlert>
        </VCol>
      </VRow>
    </div>

    <!-- Mensaje de error general de ítems si existe -->
    <VAlert
      v-if="errors.items"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-3"
    >
      {{ errors.items }}
    </VAlert>

    <!-- Tabla de ítems -->
    <VTable
      density="compact"
      class="border rounded text-no-wrap"
    >
      <thead>
        <tr>
          <th style="width: 40px;">
            #
          </th>
          <th>ARTÍCULO / PRESENTACIÓN</th>
          <th
            v-if="noteType !== 'in'"
            class="text-end"
            style="width: 120px;"
          >
            DISPONIBLE
          </th>
          <th
            class="text-end"
            style="width: 140px;"
          >
            CANTIDAD *
          </th>
          <th
            v-if="noteType === 'in'"
            class="text-end"
            style="width: 140px;"
          >
            COSTO UNIT. (S/) *
          </th>
          <th
            class="text-end"
            style="width: 130px;"
          >
            SUBTOTAL (S/)
          </th>
          <th style="min-width: 180px;">
            NOTAS
          </th>
          <th
            v-if="!readonly"
            class="text-center"
            style="width: 60px;"
          >
            ACCIÓN
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="lines.length === 0">
          <td
            :colspan="readonly ? (noteType === 'in' ? 5 : 5) : (noteType === 'in' ? 6 : 6)"
            class="text-center py-6 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-box-off"
              size="28"
              class="mb-1"
            />
            <div>No hay artículos agregados a la nota.</div>
          </td>
        </tr>
        <tr
          v-for="(line, idx) in lines"
          :key="line.item_presentation_id || idx"
        >
          <!-- # -->
          <td>{{ idx + 1 }}</td>

          <!-- Nombre y presentación -->
          <td>
            <div class="font-weight-medium text-high-emphasis">
              {{ line.name || line.catalog_item?.name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ line.code || line.catalog_item?.code }} · Presentación: {{ line.presentation_name || line.presentation?.name }}
            </div>
            <div
              v-if="errors[`items.${idx}.catalog_item_id`] || errors[`items.${idx}.item_presentation_id`]"
              class="text-error text-caption"
            >
              {{ errors[`items.${idx}.catalog_item_id`] || errors[`items.${idx}.item_presentation_id`] }}
            </div>
          </td>

          <!-- Stock disponible (out/transfer) -->
          <td
            v-if="noteType !== 'in'"
            class="text-end"
          >
            <span :class="line.stock_available !== null && line.stock_available < line.quantity ? 'text-error font-weight-bold' : ''">
              {{ line.stock_available !== null ? line.stock_available : '—' }} {{ line.unit_symbol }}
            </span>
          </td>

          <!-- Cantidad -->
          <td class="text-end">
            <template v-if="!readonly">
              <VTextField
                v-model.number="line.quantity"
                type="number"
                min="0.0001"
                :step="line.allow_decimals ? '0.001' : '1'"
                density="compact"
                hide-details="auto"
                style="max-inline-size: 130px; margin-inline-start: auto;"
                placeholder="1"
                :error="Boolean(errors[`items.${idx}.quantity`])"
                @update:model-value="updateLineSubtotal(line, idx)"
              />
              <div
                v-if="errors[`items.${idx}.quantity`]"
                class="text-error text-caption text-end"
              >
                {{ errors[`items.${idx}.quantity`] }}
              </div>
            </template>
            <template v-else>
              <span class="font-weight-medium">{{ line.quantity }} {{ line.unit_symbol }}</span>
            </template>
          </td>

          <!-- Costo Unitario (solo in) -->
          <td
            v-if="noteType === 'in'"
            class="text-end"
          >
            <template v-if="!readonly">
              <VTextField
                v-model.number="line.unit_cost"
                type="number"
                min="0"
                step="0.01"
                density="compact"
                hide-details="auto"
                style="max-inline-size: 130px; margin-inline-start: auto;"
                placeholder="0.00"
                :error="Boolean(errors[`items.${idx}.unit_cost`])"
                @update:model-value="updateLineSubtotal(line, idx)"
              />
              <div
                v-if="errors[`items.${idx}.unit_cost`]"
                class="text-error text-caption text-end"
              >
                {{ errors[`items.${idx}.unit_cost`] }}
              </div>
            </template>
            <template v-else>
              <span>S/ {{ formatCurrency(line.unit_cost) }}</span>
            </template>
          </td>

          <!-- Subtotal -->
          <td class="text-end font-weight-medium">
            S/ {{ formatCurrency(line.total_cost) }}
          </td>

          <!-- Notas de línea -->
          <td>
            <template v-if="!readonly">
              <VTextField
                v-model="line.notes"
                placeholder="Obs. opcional"
                density="compact"
                hide-details
              />
            </template>
            <template v-else>
              <span class="text-caption text-medium-emphasis">{{ line.notes || '—' }}</span>
            </template>
          </td>

          <!-- Acción eliminar -->
          <td
            v-if="!readonly"
            class="text-center"
          >
            <VBtn
              icon
              size="x-small"
              color="error"
              variant="text"
              @click="removeLine(idx)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </td>
        </tr>
      </tbody>
      <tfoot v-if="lines.length > 0">
        <tr class="bg-var-theme-background font-weight-bold">
          <td colspan="2">
            Totales: {{ lines.length }} ítem(s)
          </td>
          <td
            v-if="noteType !== 'in'"
            class="text-end"
          />
          <td class="text-end">
            {{ totalUnits }}
          </td>
          <td
            v-if="noteType === 'in'"
            class="text-end"
          />
          <td class="text-end text-primary">
            S/ {{ formatCurrency(grandTotal) }}
          </td>
          <td :colspan="readonly ? 1 : 2" />
        </tr>
      </tfoot>
    </VTable>
  </div>
</template>
