<script setup>
/* eslint-disable camelcase */
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { useStockStore } from '@/modules/inventory/stores/stockStore'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  stockItem: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'saved'])

const stockStore = useStockStore()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const form = ref({ min_stock: 0, max_stock: null })
const isSubmitting = ref(false)

watch(() => [props.stockItem, props.isDrawerOpen], () => {
  if (props.stockItem && props.isDrawerOpen) {
    form.value = {
      min_stock: props.stockItem.min_stock ?? 0,
      max_stock: props.stockItem.max_stock ?? null,
    }
    clearErrors()
  }
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = async () => {
  clearErrors()

  const min = Number(form.value.min_stock)
  if (isNaN(min) || min < 0) {
    errors.value.min_stock = 'El stock mínimo debe ser un número mayor o igual a 0'

    return
  }

  let max = null
  if (form.value.max_stock !== null && form.value.max_stock !== '') {
    max = Number(form.value.max_stock)
    if (isNaN(max) || max < min) {
      errors.value.max_stock = 'El stock máximo debe ser mayor o igual al stock mínimo'

      return
    }
  }

  isSubmitting.value = true
  try {
    const updated = await stockStore.updateLimits({
      warehouse_id: props.stockItem.warehouse?.id,
      catalog_item_id: props.stockItem.catalog_item?.id,
      min_stock: min,
      max_stock: max,
    })

    notify.success('Límites de stock actualizados correctamente')
    emit('saved', updated)
    handleClose()
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="420"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      title="Configurar Límites de Stock"
      icon="tabler-adjustments"
      @close="handleClose"
    />

    <VCard
      v-if="props.stockItem"
      flat
    >
      <VCardText>
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ props.stockItem.catalog_item?.code }} · {{ props.stockItem.catalog_item?.name }}
        </p>

        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <VTextField
                :model-value="props.stockItem.warehouse?.name"
                label="Almacén"
                placeholder="Almacén Central"
                readonly
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model.number="form.min_stock"
                label="Stock mínimo *"
                placeholder="10"
                type="number"
                min="0"
                step="any"
                :error-messages="errors.min_stock"
                @input="clearFieldError('min_stock')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model.number="form.max_stock"
                label="Stock máximo"
                placeholder="50"
                type="number"
                min="0"
                step="any"
                clearable
                :error-messages="errors.max_stock"
                @input="clearFieldError('max_stock')"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="isSubmitting"
                :disabled="isSubmitting"
              >
                Guardar Cambios
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                @click="handleClose"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VNavigationDrawer>
</template>
