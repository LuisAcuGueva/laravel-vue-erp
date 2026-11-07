<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import ProviderPriceCompareDialog from '@/modules/purchases/components/providers/ProviderPriceCompareDialog.vue'
import { providersApi } from '@/modules/purchases/services/providersApi'

const props = defineProps({
  provider: {
    type: Object,
    required: true,
  },
})

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const prices = ref([])
const isLoading = ref(false)
const currencyOptions = ref([])

const isDialogOpen = ref(false)
const isSaving = ref(false)
const deletingId = ref(null)
const editingId = ref(null)
const initialItem = ref(null)
const form = ref({})

const isCompareOpen = ref(false)
const comparePresentationId = ref(null)
const compareTitle = ref('')

const isEditing = computed(() => Boolean(editingId.value))

const emptyForm = () => ({
  item_presentation_id: null,
  currency_id: props.provider.default_currency_id || null,
  price: '',
  valid_from: new Date().toISOString().slice(0, 10),
  valid_until: '',
  notes: '',
})

const loadPrices = async () => {
  isLoading.value = true
  try {
    const res = await providersApi.listPrices(props.provider.id)

    prices.value = res?.data || []
  }
  catch {
    notify.error('No se pudieron cargar los precios del proveedor.')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  loadPrices()
  try {
    const res = await currenciesApi.options()

    currencyOptions.value = (res?.data || []).map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id }))
  }
  catch {
    currencyOptions.value = []
  }
})

const describePresentation = price => {
  const item = price.presentation?.item

  return item ? `${item.code} · ${item.name} (${price.presentation.name})` : price.presentation?.name || '—'
}

const openCreate = () => {
  editingId.value = null
  initialItem.value = null
  form.value = emptyForm()
  clearErrors()
  isDialogOpen.value = true
}

const openEdit = price => {
  editingId.value = price.id
  initialItem.value = {
    presentation_id: price.item_presentation_id,
    code: price.presentation?.item?.code || '',
    name: price.presentation?.item?.name || '',
    presentation_name: price.presentation?.name || '',
  }
  form.value = {
    item_presentation_id: price.item_presentation_id,
    currency_id: price.currency_id,
    price: price.price,
    valid_from: price.valid_from,
    valid_until: price.valid_until || '',
    notes: price.notes || '',
  }
  clearErrors()
  isDialogOpen.value = true
}

const openCompare = (presentationId, title) => {
  comparePresentationId.value = presentationId
  compareTitle.value = title
  isCompareOpen.value = true
}

const save = async () => {
  isSaving.value = true
  clearErrors()
  try {
    const payload = {
      item_presentation_id: form.value.item_presentation_id,
      currency_id: form.value.currency_id,
      price: form.value.price === '' ? null : form.value.price,
      valid_from: form.value.valid_from || null,
      valid_until: form.value.valid_until || null,
      notes: form.value.notes?.trim() || null,
    }

    if (isEditing.value)
      await providersApi.updatePrice(editingId.value, payload)
    else
      await providersApi.createPrice(props.provider.id, payload)

    notify.success(`Precio ${isEditing.value ? 'actualizado' : 'registrado'} correctamente`)
    isDialogOpen.value = false
    await loadPrices()
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSaving.value = false
  }
}

const removePrice = async price => {
  if (!await confirmDelete({ entity: 'precio', name: describePresentation(price) }))
    return

  deletingId.value = price.id
  try {
    await providersApi.deletePrice(price.id)
    notify.success('Precio eliminado correctamente')
    await loadPrices()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el precio.' })
  }
  finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <VCardText>
      <div
        v-if="can('providers.update')"
        class="d-flex justify-end mb-4"
      >
        <VBtn
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nuevo precio
        </VBtn>
      </div>

      <VProgressLinear
        v-if="isLoading"
        indeterminate
        class="mb-4"
      />

      <VTable density="comfortable">
        <thead>
          <tr>
            <th>ARTÍCULO / PRESENTACIÓN</th>
            <th class="text-end">
              PRECIO
            </th>
            <th>VIGENCIA</th>
            <th class="text-end">
              ÚLTIMA COMPRA
            </th>
            <th class="text-center">
              ACCIONES
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!prices.length && !isLoading">
            <td
              colspan="5"
              class="text-center text-medium-emphasis py-6"
            >
              Este proveedor aún no tiene precios registrados.
            </td>
          </tr>
          <tr
            v-for="price in prices"
            :key="price.id"
          >
            <td>{{ describePresentation(price) }}</td>
            <td class="text-end font-weight-medium">
              {{ price.currency?.symbol }} {{ Number(price.price).toFixed(2) }}
            </td>
            <td>{{ price.valid_from }} → {{ price.valid_until || 'Indefinida' }}</td>
            <td class="text-end">
              <span v-if="price.last_purchase_price">{{ price.currency?.symbol }} {{ Number(price.last_purchase_price).toFixed(2) }}</span>
              <span
                v-else
                class="text-medium-emphasis"
              >—</span>
            </td>
            <td class="text-center">
              <div class="d-flex justify-center gap-2">
                <RowActionButton
                  action="custom"
                  icon="tabler-scale"
                  tooltip="Comparar precios"
                  @click="openCompare(price.item_presentation_id, describePresentation(price))"
                />
                <RowActionButton
                  v-if="can('providers.update')"
                  action="edit"
                  tooltip="Editar precio"
                  @click="openEdit(price)"
                />
                <RowActionButton
                  v-if="can('providers.update')"
                  action="delete"
                  tooltip="Eliminar precio"
                  :loading="deletingId === price.id"
                  @click="removePrice(price)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <VDialog
      v-model="isDialogOpen"
      max-width="560"
    >
      <VCard>
        <VCardTitle class="pt-4">
          {{ isEditing ? 'Editar precio' : 'Nuevo precio' }}
        </VCardTitle>

        <VCardText>
          <VAlert
            v-if="generalError"
            type="error"
            density="compact"
            class="mb-4"
          >
            {{ generalError }}
          </VAlert>

          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12">
                <CatalogItemPicker
                  v-model="form.item_presentation_id"
                  context="purchase"
                  label="Artículo / Presentación *"
                  :initial-item="initialItem"
                  :error-messages="errors.item_presentation_id"
                  @update:model-value="clearFieldError('item_presentation_id')"
                />
                <VBtn
                  v-if="form.item_presentation_id"
                  size="small"
                  variant="text"
                  prepend-icon="tabler-scale"
                  class="mt-1"
                  @click="openCompare(form.item_presentation_id, 'Precios vigentes de todos los proveedores')"
                >
                  Comparar precios
                </VBtn>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="form.currency_id"
                  :items="currencyOptions"
                  label="Moneda *"
                  placeholder="PEN (S/)"
                  :error-messages="errors.currency_id"
                  @update:model-value="clearFieldError('currency_id')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.price"
                  label="Precio *"
                  placeholder="12.5000"
                  type="number"
                  min="0"
                  step="0.0001"
                  :error-messages="errors.price"
                  @input="clearFieldError('price')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.valid_from"
                  label="Vigente desde *"
                  type="date"
                  :error-messages="errors.valid_from"
                  @input="clearFieldError('valid_from')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.valid_until"
                  label="Vigente hasta"
                  type="date"
                  hint="Vacío = vigente indefinidamente"
                  persistent-hint
                  :error-messages="errors.valid_until"
                  @input="clearFieldError('valid_until')"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="form.notes"
                  label="Notas"
                  placeholder="Precio por volumen, mínimo 100 unidades"
                  maxlength="500"
                  :error-messages="errors.notes"
                  @input="clearFieldError('notes')"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSaving"
            @click="isDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSaving"
            @click="save"
          >
            {{ isEditing ? 'Guardar cambios' : 'Registrar precio' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <ProviderPriceCompareDialog
      v-model="isCompareOpen"
      :presentation-id="comparePresentationId"
      :title="compareTitle"
    />
  </div>
</template>
