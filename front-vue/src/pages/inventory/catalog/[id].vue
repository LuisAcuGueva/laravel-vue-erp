<script setup>
/* eslint-disable camelcase */
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import PresentationsPricesTab from '@/modules/inventory/components/catalog/PresentationsPricesTab.vue'
import { catalogApi } from '@/modules/inventory/services/catalogApi'
import { useInventorySettingsStore } from '@/modules/inventory/stores/inventorySettingsStore'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    action: 'catalog.view',
    title: 'Ficha del Artículo',
    icon: 'tabler-box',
    description: 'Ficha técnica detallada, presentaciones y precios de venta.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation({ name: 'inventory-catalog' })
const settingsStore = useInventorySettingsStore()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const itemId = route.params.id
const item = ref(null)
const isLoading = ref(true)
const isSaving = ref(false)
const activeTab = ref('general')

const categories = ref([])
const brands = ref([])
const units = ref([])

const form = ref({
  name: '',
  code: '',
  type: 'product',
  category_id: null,
  brand_id: null,
  base_unit_id: null,
  tax_type: 'taxable',
  description: '',
  track_stock: true,
  is_sellable: true,
  is_purchasable: true,
  is_active: true,
})

const typeOptions = [
  { value: 'product', title: 'Producto (Bien tangible con stock)' },
  { value: 'service', title: 'Servicio (Intangible sin stock)' },
]

const taxTypeOptions = [
  { value: 'taxable', title: 'Gravado (Operación Onerosa / IGV)' },
  { value: 'exempt', title: 'Exonerado de IGV' },
  { value: 'unaffected', title: 'Inafecto de IGV' },
]

const loadItem = async () => {
  isLoading.value = true
  try {
    const res = await catalogApi.get(itemId)

    item.value = res?.data || null
    if (item.value) {
      form.value = {
        name: item.value.name || '',
        code: item.value.code || '',
        type: item.value.type || 'product',
        category_id: item.value.category_id || null,
        brand_id: item.value.brand_id || null,
        base_unit_id: item.value.base_unit_id || null,
        tax_type: item.value.tax_type || 'taxable',
        description: item.value.description || '',
        track_stock: Boolean(item.value.track_stock),
        is_sellable: Boolean(item.value.is_sellable),
        is_purchasable: Boolean(item.value.is_purchasable),
        is_active: Boolean(item.value.is_active),
      }
    }
  }
  catch (err) {
    notify.error('No se pudo cargar el artículo')
    router.push({ name: 'inventory-catalog' })
  }
  finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([
    loadItem(),
    settingsStore.fetchCategoryOptions().then(r => categories.value = r || []),
    settingsStore.fetchBrandOptions().then(r => brands.value = r || []),
    settingsStore.fetchUnitOptions().then(r => units.value = r || []),
  ])
})

const handleSaveGeneral = async () => {
  isSaving.value = true
  clearErrors()
  try {
    const payload = {
      name: form.value.name.trim(),
      code: form.value.code?.trim() || null,
      type: form.value.type,
      category_id: form.value.category_id,
      brand_id: form.value.brand_id || null,
      base_unit_id: form.value.base_unit_id,
      tax_type: form.value.tax_type,
      description: form.value.description?.trim() || null,
      track_stock: form.value.type === 'product' ? Boolean(form.value.track_stock) : false,
      is_sellable: Boolean(form.value.is_sellable),
      is_purchasable: Boolean(form.value.is_purchasable),
      is_active: Boolean(form.value.is_active),
    }

    await catalogApi.update(itemId, payload)
    notify.success('Ficha técnica actualizada correctamente')
    await loadItem()
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="catalog-detail-page">
    <VCard>
      <VCardText>
        <PageHeader
          :title="item?.name || 'Cargando artículo...'"
          :description="item?.code ? `Código: ${item.code}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="item"
              :color="item.is_active ? 'success' : 'secondary'"
              size="small"
              class="text-uppercase"
            >
              {{ item.is_active ? 'Activo' : 'Inactivo' }}
            </VChip>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="goBack"
            >
              Volver
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>

      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          value="general"
          prepend-icon="tabler-info-circle"
        >
          Ficha General
        </VTab>
        <VTab
          value="presentations"
          prepend-icon="tabler-currency-dollar"
        >
          Presentaciones y Precios
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <!-- PESTAÑA 1: FICHA GENERAL -->
        <VWindowItem value="general">
          <VCardText>
            <VAlert
              v-if="generalError"
              type="error"
              density="compact"
              class="mb-4"
            >
              {{ generalError }}
            </VAlert>

            <VForm @submit.prevent="handleSaveGeneral">
              <VRow>
                <VCol
                  cols="12"
                  md="8"
                >
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
                  md="4"
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
                  md="4"
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
                  md="4"
                >
                  <VAutocomplete
                    v-model="form.category_id"
                    :items="categories"
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
                  md="4"
                >
                  <VAutocomplete
                    v-model="form.brand_id"
                    :items="brands"
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
                  md="6"
                >
                  <VAutocomplete
                    v-model="form.base_unit_id"
                    :items="units"
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
                  md="6"
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
                  <VTextarea
                    v-model="form.description"
                    label="Descripción y Especificaciones"
                    placeholder="Ficha técnica o detalles del artículo"
                    rows="3"
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
                    />
                    <VSwitch
                      v-model="form.is_sellable"
                      label="Disponible para Venta"
                      color="primary"
                    />
                    <VSwitch
                      v-model="form.is_purchasable"
                      label="Disponible para Compra"
                      color="primary"
                    />
                    <VSwitch
                      v-model="form.is_active"
                      label="Activo"
                      color="success"
                    />
                  </div>
                </VCol>

                <VCol
                  v-if="can('catalog.update')"
                  cols="12"
                  class="d-flex gap-4 mt-2"
                >
                  <VBtn
                    type="submit"
                    color="primary"
                    :loading="isSaving"
                    prepend-icon="tabler-device-floppy"
                  >
                    Guardar Cambios
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VWindowItem>

        <!-- PESTAÑA 2: PRESENTACIONES Y PRECIOS -->
        <VWindowItem value="presentations">
          <VCardText>
            <PresentationsPricesTab
              v-if="item"
              :item="item"
              :units="units"
              @refresh="loadItem"
            />
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>
