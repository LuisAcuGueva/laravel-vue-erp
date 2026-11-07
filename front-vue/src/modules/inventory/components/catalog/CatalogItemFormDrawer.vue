<script setup>
/* eslint-disable camelcase */
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  itemData: {
    type: Object,
    default: null,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  brands: {
    type: Array,
    default: () => [],
  },
  units: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.itemData?.id))

const typeOptions = [
  { value: 'product', title: 'Producto (Bien tangible con stock)' },
  { value: 'service', title: 'Servicio (Intangible sin stock)' },
]

const taxTypeOptions = [
  { value: 'taxable', title: 'Gravado (Operación Onerosa / IGV)' },
  { value: 'exempt', title: 'Exonerado de IGV' },
  { value: 'unaffected', title: 'Inafecto de IGV' },
]

const defaultForm = {
  name: '',
  code: '',
  type: 'product',
  category_id: null,
  brand_id: null,
  base_unit_id: null,
  tax_type: 'taxable',
  barcode: '',
  description: '',
  track_stock: true,
  is_sellable: true,
  is_purchasable: true,
  is_active: true,
  retail_price: null,
  wholesale_price: null,
  wholesale_min_quantity: 3,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.itemData], () => {
  const val = props.itemData
  if (val) {
    const defaultPres = val.default_presentation || val.presentations?.[0]
    const retailP = defaultPres?.prices?.find(p => p.price_type === 'retail')
    const wholesaleP = defaultPres?.prices?.find(p => p.price_type === 'wholesale')

    form.value = {
      name: val.name || '',
      code: val.code || '',
      type: val.type || 'product',
      category_id: val.category_id || null,
      brand_id: val.brand_id || null,
      base_unit_id: val.base_unit_id || null,
      tax_type: val.tax_type || 'taxable',
      barcode: defaultPres?.barcode || '',
      description: val.description || '',
      track_stock: val.track_stock !== undefined ? Boolean(val.track_stock) : true,
      is_sellable: val.is_sellable !== undefined ? Boolean(val.is_sellable) : true,
      is_purchasable: val.is_purchasable !== undefined ? Boolean(val.is_purchasable) : true,
      is_active: val.is_active !== undefined ? Boolean(val.is_active) : true,
      retail_price: retailP?.price ?? null,
      wholesale_price: wholesaleP?.price ?? null,
      wholesale_min_quantity: wholesaleP?.min_quantity ?? 3,
    }
  }
  else {
    form.value = { ...defaultForm }
  }
  clearErrors()
}, { immediate: true })

watch(() => form.value.type, newType => {
  if (newType === 'service') {
    form.value.track_stock = false
  }
})

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()

  const payload = {
    id: props.itemData?.id,
    name: form.value.name.trim(),
    code: form.value.code?.trim() || null,
    type: form.value.type,
    category_id: form.value.category_id,
    brand_id: form.value.brand_id || null,
    base_unit_id: form.value.base_unit_id,
    tax_type: form.value.tax_type,
    barcode: form.value.barcode?.trim() || null,
    description: form.value.description?.trim() || null,
    track_stock: form.value.type === 'product' ? Boolean(form.value.track_stock) : false,
    is_sellable: Boolean(form.value.is_sellable),
    is_purchasable: Boolean(form.value.is_purchasable),
    is_active: Boolean(form.value.is_active),
  }

  // Precios para creación
  if (!isEditing.value) {
    const prices = []
    if (form.value.retail_price !== null && form.value.retail_price !== '') {
      prices.push({
        price_type: 'retail',
        price: Number(form.value.retail_price),
        min_quantity: 1,
      })
    }
    if (form.value.wholesale_price !== null && form.value.wholesale_price !== '') {
      prices.push({
        price_type: 'wholesale',
        price: Number(form.value.wholesale_price),
        min_quantity: Number(form.value.wholesale_min_quantity || 1),
      })
    }
    if (prices.length > 0) {
      payload.prices = prices
    }
  }

  emit('submit', payload)
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="540"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Artículo' : 'Nuevo Artículo en Catálogo'"
      icon="tabler-box"
      @close="handleClose"
    />

    <VCardText class="pb-6">
      <VAlert
        v-if="generalError"
        type="error"
        density="compact"
        class="mb-4"
      >
        {{ generalError }}
      </VAlert>

      <VForm @submit.prevent="handleSubmit">
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="form.name"
              label="Nombre del Artículo *"
              placeholder="Laptop HP ProBook 450 G9"
              maxlength="200"
              :error-messages="errors.name"
              @input="clearFieldError('name')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.code"
              label="Código / SKU"
              placeholder="ITM-000001"
              maxlength="30"
              :error-messages="errors.code"
              @input="clearFieldError('code')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSelect
              v-model="form.type"
              :items="typeOptions"
              label="Tipo de Ítem *"
              :error-messages="errors.type"
              @update:model-value="clearFieldError('type')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.category_id"
              :items="props.categories"
              item-title="name"
              item-value="id"
              label="Categoría *"
              placeholder="Electrónica"
              clearable
              :error-messages="errors.category_id"
              @update:model-value="clearFieldError('category_id')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.brand_id"
              :items="props.brands"
              item-title="name"
              item-value="id"
              label="Marca"
              placeholder="Samsung"
              clearable
              :error-messages="errors.brand_id"
              @update:model-value="clearFieldError('brand_id')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.base_unit_id"
              :items="props.units"
              :item-title="u => `${u.name} (${u.symbol})`"
              item-value="id"
              label="Unidad Base *"
              placeholder="Unidad (UND)"
              clearable
              :error-messages="errors.base_unit_id"
              @update:model-value="clearFieldError('base_unit_id')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSelect
              v-model="form.tax_type"
              :items="taxTypeOptions"
              label="Afectación Tributaria *"
              :error-messages="errors.tax_type"
              @update:model-value="clearFieldError('tax_type')"
            />
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="form.barcode"
              label="Código de Barras"
              placeholder="7751234567890"
              maxlength="50"
              :error-messages="errors.barcode"
              @input="clearFieldError('barcode')"
            />
          </VCol>

          <!-- Precios iniciales solo en creación -->
          <template v-if="!isEditing">
            <VCol cols="12">
              <VDivider class="mb-2" />
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Precios iniciales (presentación base)
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.retail_price"
                type="number"
                step="0.01"
                min="0"
                label="Precio Minorista"
                placeholder="120.00"
                :error-messages="errors['prices.0.price']"
                @input="clearFieldError('prices.0.price')"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.wholesale_price"
                type="number"
                step="0.01"
                min="0"
                label="Precio Mayorista"
                placeholder="105.00"
                :error-messages="errors['prices.1.price']"
                @input="clearFieldError('prices.1.price')"
              />
            </VCol>

            <VCol
              v-if="form.wholesale_price"
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.wholesale_min_quantity"
                type="number"
                step="1"
                min="1"
                label="Cantidad Mín. Mayorista"
                placeholder="3"
                :error-messages="errors['prices.1.min_quantity']"
              />
            </VCol>
          </template>

          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="Descripción / Ficha Técnica"
              placeholder="Especificaciones o notas internas del producto o servicio"
              rows="2"
              maxlength="2000"
              :error-messages="errors.description"
              @input="clearFieldError('description')"
            />
          </VCol>

          <VCol cols="12">
            <VDivider class="my-2" />
            <div class="d-flex flex-wrap gap-4">
              <VSwitch
                v-if="form.type === 'product'"
                v-model="form.track_stock"
                label="Controlar Stock"
                color="primary"
                density="compact"
              />
              <VSwitch
                v-model="form.is_sellable"
                label="Para Venta"
                color="primary"
                density="compact"
              />
              <VSwitch
                v-model="form.is_purchasable"
                label="Para Compra"
                color="primary"
                density="compact"
              />
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                density="compact"
              />
            </div>
          </VCol>

          <VCol
            cols="12"
            class="d-flex gap-4 mt-2"
          >
            <VBtn
              type="submit"
              :loading="props.isSubmitting"
              :disabled="props.isSubmitting"
            >
              {{ isEditing ? 'Guardar Cambios' : 'Registrar Ítem' }}
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="props.isSubmitting"
              @click="handleClose"
            >
              Cancelar
            </VBtn>
          </VCol>
        </VRow>
      </VForm>
    </VCardText>
  </VNavigationDrawer>
</template>
