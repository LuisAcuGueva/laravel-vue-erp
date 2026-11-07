<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { categoriesApi, warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { areasApi } from '@/modules/maintenance/services/areasApi'
import PurchaseRequestForm from '@/modules/purchases/components/requests/PurchaseRequestForm.vue'
import { purchaseRequestsApi } from '@/modules/purchases/services/purchaseRequestsApi'

definePage({
  meta: {
    action: 'purchase_requests.create',
    title: 'Nuevo requerimiento',
    icon: 'tabler-clipboard-list',
    description: 'Pide productos o servicios indicando cantidad, fecha requerida y almacén de destino.',
  },
})

const router = useRouter()
const notify = useNotify()

const formRef = ref(null)
const isSubmitting = ref(false)

const handleSubmit = async ({ action, payload }) => {
  isSubmitting.value = true
  formRef.value?.clearErrors()
  try {
    const res = await purchaseRequestsApi.create(payload)
    const id = res?.data?.id

    if (action === 'send' && id) {
      await purchaseRequestsApi.submit(id)
      notify.success('Requerimiento enviado correctamente')
    }
    else {
      notify.success('Requerimiento creado correctamente')
    }

    router.push({ name: 'purchases-requests-id', params: { id } })
  }
  catch (err) {
    formRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

// --- Generar desde stock bajo mínimo ---
const isLowStockOpen = ref(false)
const isGenerating = ref(false)
const lowStock = ref({ warehouse_id: null, area_id: null, category_id: null })
const lowStockError = ref('')
const lowStockFieldErrors = ref({})
const areaOptions = ref([])
const warehouseOptions = ref([])
const categoryOptions = ref([])
const { setErrors, errors, clearErrors } = useApiFormErrors()

onMounted(async () => {
  try {
    const [areasRes, warehousesRes, categoriesRes] = await Promise.all([
      areasApi.options(),
      warehousesApi.options(),
      categoriesApi.options(),
    ])

    areaOptions.value = (areasRes?.data || []).map(a => ({ title: a.name, value: a.id }))
    warehouseOptions.value = (warehousesRes?.data || []).map(w => ({ title: `${w.code} - ${w.name}`, value: w.id }))
    categoryOptions.value = (categoriesRes?.data || []).map(c => ({ title: c.name, value: c.id }))
  }
  catch {
    // Los combos quedan vacíos; el backend valida igualmente.
  }
})

const generateFromLowStock = async () => {
  isGenerating.value = true
  lowStockError.value = ''
  clearErrors()
  try {
    const res = await purchaseRequestsApi.fromLowStock({
      'warehouse_id': lowStock.value.warehouse_id,
      'area_id': lowStock.value.area_id,
      'category_id': lowStock.value.category_id || undefined,
    })

    notify.success('Requerimiento generado desde stock bajo mínimo')
    isLowStockOpen.value = false
    router.push({ name: 'purchases-requests-id', params: { id: res?.data?.id } })
  }
  catch (err) {
    setErrors(err)
    lowStockFieldErrors.value = errors.value
    lowStockError.value = err?.data?.message || 'No se pudo generar el requerimiento.'
  }
  finally {
    isGenerating.value = false
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader>
          <template #actions>
            <VBtn
              variant="tonal"
              color="warning"
              prepend-icon="tabler-alert-triangle"
              @click="isLowStockOpen = true"
            >
              Generar desde stock bajo mínimo
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="router.push({ name: 'purchases-requests' })"
            >
              Volver
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <PurchaseRequestForm
          ref="formRef"
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="router.push({ name: 'purchases-requests' })"
        />
      </VCardText>
    </VCard>

    <VDialog
      v-model="isLowStockOpen"
      max-width="520"
    >
      <VCard>
        <VCardTitle class="pt-4">
          Generar desde stock bajo mínimo
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Se crea un borrador con una línea por cada artículo cuyo stock está por debajo del mínimo,
            sugiriendo la cantidad para llegar al máximo.
          </p>

          <VAlert
            v-if="lowStockError"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ lowStockError }}
          </VAlert>

          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-model="lowStock.warehouse_id"
                :items="warehouseOptions"
                label="Almacén *"
                placeholder="ALM-01 - Almacén Central"
                :error-messages="lowStockFieldErrors.warehouse_id"
              />
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="lowStock.area_id"
                :items="areaOptions"
                label="Área solicitante *"
                placeholder="Mantenimiento"
                :error-messages="lowStockFieldErrors.area_id"
              />
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="lowStock.category_id"
                :items="categoryOptions"
                label="Categoría"
                placeholder="Materiales de construcción"
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isGenerating"
            @click="isLowStockOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isGenerating"
            :disabled="!lowStock.warehouse_id || !lowStock.area_id"
            @click="generateFromLowStock"
          >
            Generar borrador
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
