<script setup>
/* eslint-disable camelcase */
import { computed, watch } from 'vue'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { calculateDocumentTotals, formatAmount } from '@/utils/documentTotals'
import { TAX_TYPE_OPTIONS } from '@/utils/options'

/**
 * DocumentLinesTable.vue
 *
 * Grilla editable de líneas de documentos comerciales (OC, facturas, cotizaciones, ventas, notas de crédito).
 * Muestra una vista previa de totales con la misma fórmula del backend (`utils/documentTotals.js`);
 * lo que se guarda es lo que devuelve la API.
 *
 * Cada línea: { key, item_presentation_id, initialItem, description, quantity, unit_price, discount_percent,
 *               tax_type, purchase_request_item_id, request_number }.
 *
 * @prop {Array} modelValue Líneas del documento (las ediciones de campo mutan los objetos de la línea)
 * @prop {'sale'|'purchase'|'stock'} context Contexto del `CatalogItemPicker`
 * @prop {boolean} pricesIncludeTax Si los precios ya incluyen el impuesto
 * @prop {number|string} taxRate Tasa de impuesto en porcentaje
 * @prop {boolean} readonly Solo lectura (sin inputs ni botones)
 * @prop {boolean} allowFreeText Permite líneas con descripción libre (servicios)
 * @prop {object} errors Errores de la API por campo (`items.0.quantity`)
 * @prop {string} currencySymbol Símbolo de la moneda del documento
 * @prop {number} currencyDecimals Decimales de la moneda del documento (`currencies.decimals`)
 */
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  context: {
    type: String,
    default: 'purchase',
  },
  pricesIncludeTax: {
    type: Boolean,
    default: false,
  },
  taxRate: {
    type: [Number, String],
    default: 18,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  allowFreeText: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
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

const emit = defineEmits(['update:modelValue', 'totals'])

let keySeed = 0
const newKey = () => `line-${Date.now()}-${keySeed++}`

const rows = computed(() => props.modelValue)

const totals = computed(() => calculateDocumentTotals(
  rows.value.map(row => ({
    quantity: row.quantity,
    unitPrice: row.unit_price,
    discountPercent: row.discount_percent || 0,
    taxType: row.tax_type,
  })),
  { taxRate: props.taxRate, pricesIncludeTax: props.pricesIncludeTax },
))

watch(totals, value => emit('totals', value), { immediate: true })

const blankLine = (free = false) => ({
  key: newKey(),
  item_presentation_id: null,
  initialItem: null,
  description: free ? '' : null,
  is_free: free,
  quantity: '1',
  unit_price: '',
  discount_percent: '0',
  tax_type: 'taxable',
  purchase_request_item_id: null,
  request_number: null,
})

const addLine = (free = false) => {
  emit('update:modelValue', [...rows.value, blankLine(free)])
}

const removeLine = index => {
  emit('update:modelValue', rows.value.filter((_, i) => i !== index))
}

const isFree = row => row.is_free || (!row.item_presentation_id && row.description !== null && row.description !== undefined && !row.initialItem)

// Al elegir un artículo se heredan su afectación tributaria y su unidad.
const onSelected = (row, item) => {
  if (!item)
    return

  row.tax_type = item.tax_type || row.tax_type
  row.unit = item.unit?.symbol || null
  row.initialItem = item
}

const taxLabel = value => TAX_TYPE_OPTIONS.find(o => o.value === value)?.title || value

defineExpose({ totals, addLine })
</script>

<template>
  <div class="document-lines-table">
    <div class="document-lines-table__scroll">
      <VTable density="comfortable">
        <thead>
          <tr>
            <th style="width: 36px;">
              #
            </th>
            <th style="min-width: 280px;">
              ARTÍCULO / DESCRIPCIÓN
            </th>
            <th style="width: 110px;">
              CANTIDAD
            </th>
            <th style="width: 130px;">
              P. UNITARIO
            </th>
            <th style="width: 100px;">
              DSCTO. %
            </th>
            <th style="width: 150px;">
              IMPUESTO
            </th>
            <th
              class="text-end"
              style="width: 130px;"
            >
              IMPORTE
            </th>
            <th
              v-if="!props.readonly"
              class="text-center"
              style="width: 60px;"
            />
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length">
            <td
              colspan="8"
              class="text-center text-medium-emphasis py-6"
            >
              Aún no hay líneas. Agrega un artículo para empezar.
            </td>
          </tr>

          <tr
            v-for="(row, index) in rows"
            :key="row.key || index"
          >
            <td>{{ index + 1 }}</td>

            <td>
              <template v-if="props.readonly">
                <div class="font-weight-medium">
                  {{ row.initialItem ? `${row.initialItem.code} · ${row.initialItem.name}` : row.description }}
                </div>
                <div
                  v-if="row.initialItem?.presentation_name"
                  class="text-caption text-medium-emphasis"
                >
                  {{ row.initialItem.presentation_name }}
                </div>
              </template>

              <template v-else>
                <VTextField
                  v-if="isFree(row)"
                  v-model="row.description"
                  label="Descripción del servicio *"
                  placeholder="Flete de materiales a obra"
                  density="compact"
                  maxlength="255"
                  :error-messages="errors[`items.${index}.description`]"
                />
                <CatalogItemPicker
                  v-else
                  v-model="row.item_presentation_id"
                  :context="props.context"
                  :initial-item="row.initialItem"
                  label="Artículo / Presentación *"
                  density="compact"
                  :clearable="false"
                  :error-messages="errors[`items.${index}.item_presentation_id`]"
                  @selected="onSelected(row, $event)"
                />
              </template>

              <VChip
                v-if="row.request_number"
                size="x-small"
                color="info"
                variant="tonal"
                class="mt-1"
              >
                {{ row.request_number }}
              </VChip>
            </td>

            <td>
              <span v-if="props.readonly">{{ row.quantity }}</span>
              <VTextField
                v-else
                v-model="row.quantity"
                label="Cantidad *"
                placeholder="10"
                type="number"
                min="0"
                step="0.0001"
                density="compact"
                class="text-right align-right-input"
                :error-messages="errors[`items.${index}.quantity`]"
              />
            </td>

            <td>
              <span v-if="props.readonly">{{ formatAmount(row.unit_price, props.currencySymbol, props.currencyDecimals) }}</span>
              <VTextField
                v-else
                v-model="row.unit_price"
                label="P. unitario *"
                placeholder="12.5000"
                type="number"
                min="0"
                step="0.0001"
                density="compact"
                class="text-right align-right-input"
                :error-messages="errors[`items.${index}.unit_price`]"
              />
            </td>

            <td>
              <span v-if="props.readonly">{{ row.discount_percent || 0 }}%</span>
              <VTextField
                v-else
                v-model="row.discount_percent"
                label="Dscto. %"
                placeholder="0"
                type="number"
                min="0"
                max="100"
                step="0.01"
                density="compact"
                class="text-right align-right-input"
                :error-messages="errors[`items.${index}.discount_percent`]"
              />
            </td>

            <td>
              <span v-if="props.readonly">{{ taxLabel(row.tax_type) }}</span>
              <VSelect
                v-else
                v-model="row.tax_type"
                :items="TAX_TYPE_OPTIONS"
                label="Impuesto"
                placeholder="Gravado"
                density="compact"
                :error-messages="errors[`items.${index}.tax_type`]"
              />
            </td>

            <td class="text-end font-weight-medium">
              {{ formatAmount(totals.lines[index]?.total, props.currencySymbol, props.currencyDecimals) }}
            </td>

            <td
              v-if="!props.readonly"
              class="text-center"
            >
              <RowActionButton
                action="delete"
                tooltip="Quitar línea"
                @click="removeLine(index)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <div
      v-if="!props.readonly"
      class="d-flex flex-wrap gap-3 mt-4"
    >
      <VBtn
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="addLine(false)"
      >
        Agregar artículo
      </VBtn>
      <VBtn
        v-if="props.allowFreeText"
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-text-plus"
        @click="addLine(true)"
      >
        Agregar servicio (texto libre)
      </VBtn>
      <slot name="actions" />
    </div>

    <div class="d-flex justify-end mt-6">
      <table class="document-lines-table__totals">
        <tbody>
          <tr v-if="Number(totals.taxableBase) > 0">
            <td class="text-medium-emphasis">
              Op. gravada
            </td>
            <td class="text-end">
              {{ formatAmount(totals.taxableBase, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
          <tr v-if="Number(totals.exemptBase) > 0">
            <td class="text-medium-emphasis">
              Op. exonerada
            </td>
            <td class="text-end">
              {{ formatAmount(totals.exemptBase, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
          <tr v-if="Number(totals.unaffectedBase) > 0">
            <td class="text-medium-emphasis">
              Op. inafecta
            </td>
            <td class="text-end">
              {{ formatAmount(totals.unaffectedBase, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
          <tr v-if="Number(totals.discountTotal) > 0">
            <td class="text-medium-emphasis">
              Descuentos
            </td>
            <td class="text-end">
              - {{ formatAmount(totals.discountTotal, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">
              IGV ({{ Number(props.taxRate) }}%)
            </td>
            <td class="text-end">
              {{ formatAmount(totals.taxTotal, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
          <tr class="text-h6">
            <td>Total</td>
            <td class="text-end">
              {{ formatAmount(totals.total, props.currencySymbol, props.currencyDecimals) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p
      v-if="!props.readonly"
      class="text-caption text-medium-emphasis text-end mt-1 mb-0"
    >
      Vista previa: los totales definitivos los calcula el sistema al guardar.
    </p>
  </div>
</template>

<style scoped>
.document-lines-table__scroll {
  overflow-x: auto;
}

.document-lines-table__totals {
  min-width: 280px;
  border-collapse: separate;
  border-spacing: 0 6px;
}

/* La clase utilitaria `text-right` de Vuetify alinea el campo, pero no siempre
   el texto tecleado dentro de <input>; se fuerza aquí para cantidad, precio
   unitario y descuento. */
.align-right-input :deep(input) {
  text-align: right;
}
</style>
