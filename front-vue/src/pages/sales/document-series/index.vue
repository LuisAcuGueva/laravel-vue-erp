<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { boxesApi } from '@/modules/finance/services/cashApi'
import { documentSeriesApi } from '@/modules/sales/services/documentSeriesApi'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'document_series.view',
    title: 'Series de Comprobantes',
    icon: 'tabler-numbers',
    description: 'Configuración de series de facturación electrónica y física por sede y caja.',
  },
})

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const { errors, setErrors, clearErrors } = useApiFormErrors()

const DOCUMENT_TYPE_OPTIONS = [
  { title: 'Factura', value: 'invoice' },
  { title: 'Boleta de Venta', value: 'receipt' },
  { title: 'Nota de Venta', value: 'sale_note' },
  { title: 'Nota de Crédito', value: 'credit_note' },
]

const boxes = ref([])

const isDialogOpen = ref(false)
const isEditing = ref(false)
const isSubmitting = ref(false)
const editingId = ref(null)
const seriesHasSales = ref(false)

const form = ref({
  document_type: 'receipt',
  series: '',
  last_number: 0,
  is_electronic: true,
  box_id: null,
  is_active: true,
})

const headers = [
  { title: 'TIPO', key: 'document_type', sortable: false },
  { title: 'SERIE', key: 'series', sortable: false },
  { title: 'ÚLTIMO CORRELATIVO', key: 'last_number', sortable: false },
  { title: 'ELECTRÓNICA', key: 'is_electronic', sortable: false },
  { title: 'CAJA', key: 'box_name', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'end' },
]

const loadBoxes = async () => {
  try {
    const res = await boxesApi.list({ is_active: true, per_page: 100 })

    boxes.value = res?.data || []
  }
  catch {
    boxes.value = []
  }
}

onMounted(() => {
  loadBoxes()
})

const table = useServerTable({
  fetch: params => documentSeriesApi.list(params),
  filters: {
    document_type: null,
    is_active: null,
  },
})

const openCreate = () => {
  clearErrors()
  isEditing.value = false
  editingId.value = null
  seriesHasSales.value = false
  form.value = {
    document_type: 'receipt',
    series: '',
    last_number: 0,
    is_electronic: true,
    box_id: null,
    is_active: true,
  }
  isDialogOpen.value = true
}

const openEdit = item => {
  clearErrors()
  isEditing.value = true
  editingId.value = item.id
  seriesHasSales.value = Boolean(item.has_sales)
  form.value = {
    document_type: item.document_type,
    series: item.series,
    last_number: item.last_number ?? 0,
    is_electronic: item.is_electronic,
    box_id: item.box_id,
    is_active: item.is_active,
  }
  isDialogOpen.value = true
}

const save = async () => {
  clearErrors()
  isSubmitting.value = true
  try {
    if (isEditing.value) {
      await documentSeriesApi.update(editingId.value, {
        box_id: form.value.box_id,
        is_active: form.value.is_active,
        ...(seriesHasSales.value
          ? {}
          : {
            series: form.value.series,
            last_number: form.value.last_number,
            is_electronic: form.value.is_electronic,
          }),
      })
      notify.success('Serie actualizada correctamente')
    }
    else {
      await documentSeriesApi.create({
        document_type: form.value.document_type,
        series: form.value.series,
        last_number: form.value.last_number,
        is_electronic: form.value.is_electronic,
        box_id: form.value.box_id,
        is_active: form.value.is_active,
      })
      notify.success('Serie creada correctamente')
    }
    isDialogOpen.value = false
    table.reload()
  }
  catch (e) {
    if (e?.status === 422 && e?.data?.errors)
      setErrors(e.data.errors)
    else
      notify.error(e?.data?.message || 'Error al guardar la serie.')
  }
  finally {
    isSubmitting.value = false
  }
}

const handleDelete = async item => {
  const confirmed = await confirmDelete({
    entity: 'la serie',
    name: item.series,
  })

  if (!confirmed)
    return

  try {
    await documentSeriesApi.delete(item.id)
    notify.success('Serie eliminada correctamente')
    table.reload()
  }
  catch (e) {
    alert({
      message: e?.data?.message || 'No se puede eliminar la serie seleccionada.',
    })
  }
}
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="document-series"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        :creatable="can('document_series.manage')"
        create-label="Nueva Serie"
        search-placeholder="F001"
        @refresh="table.reload"
        @create="openCreate"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.document_type"
            label="Tipo de comprobante"
            :items="DOCUMENT_TYPE_OPTIONS"
            placeholder="Factura"
            density="compact"
            clearable
            style="min-width: 200px"
          />

          <VSelect
            v-model="table.filters.is_active"
            label="Estado"
            :items="STATUS_OPTIONS"
            placeholder="Activo"
            density="compact"
            clearable
            style="min-width: 140px"
          />
        </template>

        <template #item.document_type="{ item }">
          <span class="font-weight-medium">{{ item.document_type_label }}</span>
        </template>

        <template #item.series="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
            class="font-weight-bold"
          >
            {{ item.series }}
          </VChip>
        </template>

        <template #item.last_number="{ item }">
          <span class="font-weight-medium font-mono">{{ String(item.last_number ?? 0).padStart(8, '0') }}</span>
        </template>

        <template #item.is_electronic="{ item }">
          <VChip
            size="small"
            :color="item.is_electronic ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_electronic ? 'Electrónica' : 'Física' }}
          </VChip>
        </template>

        <template #item.box_name="{ item }">
          <span v-if="item.box_name">{{ item.box_name }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >Todas las cajas</span>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'default'"
            variant="tonal"
          >
            {{ item.is_active ? 'Activa' : 'Inactiva' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <RowActionButton
              v-if="can('document_series.manage')"
              action="edit"
              tooltip="Editar serie"
              @click="openEdit(item)"
            />

            <RowActionButton
              v-if="can('document_series.manage')"
              action="delete"
              tooltip="Eliminar serie"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Diálogo Crear/Editar Serie -->
    <VDialog
      v-model="isDialogOpen"
      max-width="540"
      persistent
    >
      <VCard>
        <VCardTitle class="pa-6 pb-2">
          <span class="text-h6">{{ isEditing ? 'Editar Serie de Comprobante' : 'Nueva Serie de Comprobante' }}</span>
        </VCardTitle>

        <VCardText class="pa-6 pt-2">
          <VAlert
            v-if="isEditing && seriesHasSales"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            La serie ya tiene comprobantes emitidos. Solo puede cambiar la caja asociada y el estado de activación.
          </VAlert>

          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="form.document_type"
                  label="Tipo de comprobante *"
                  :items="DOCUMENT_TYPE_OPTIONS"
                  :disabled="isEditing"
                  :error-messages="errors.document_type"
                  placeholder="Factura"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model="form.series"
                  label="Código de serie *"
                  placeholder="Ej: F001, B001"
                  :disabled="isEditing && seriesHasSales"
                  :error-messages="errors.series"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model.number="form.last_number"
                  label="Último correlativo emitido *"
                  type="number"
                  placeholder="Ej: 0"
                  :disabled="isEditing && seriesHasSales"
                  :error-messages="errors.last_number"
                />
              </VCol>

              <VCol cols="12">
                <VAutocomplete
                  v-model="form.box_id"
                  label="Caja registradora asignada"
                  :items="boxes"
                  item-title="name"
                  item-value="id"
                  placeholder="Caja principal (vacío = todas las cajas)"
                  clearable
                  :error-messages="errors.box_id"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.is_electronic"
                  label="Facturación Electrónica"
                  color="primary"
                  :disabled="isEditing && seriesHasSales"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.is_active"
                  label="Serie activa"
                  color="success"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-6 pt-0 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSubmitting"
            @click="isDialogOpen = false"
          >
            Cancelar
          </VBtn>

          <VBtn
            color="primary"
            :loading="isSubmitting"
            @click="save"
          >
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
