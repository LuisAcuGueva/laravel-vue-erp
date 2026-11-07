<script setup>
import { ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ProviderFormDrawer from '@/modules/purchases/components/providers/ProviderFormDrawer.vue'
import { useProvidersStore } from '@/modules/purchases/stores/providersStore'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'providers.view',
    title: 'Proveedores',
    icon: 'tabler-truck-delivery',
    description: 'Directorio de proveedores con contactos, cuentas bancarias y precios',
  },
})

const router = useRouter()
const providersStore = useProvidersStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedProvider = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => providersStore.fetchProviders(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'DOCUMENTO', key: 'document_number', sortable: false, alwaysVisible: true },
  { title: 'RAZÓN SOCIAL', key: 'business_name', sortable: false },
  { title: 'CONTACTO PRINCIPAL', key: 'primary_contact', sortable: false },
  { title: 'TELÉFONO', key: 'phone', sortable: false },
  { title: 'CONDICIÓN', key: 'credit_days', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedProvider.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = provider => {
  selectedProvider.value = { ...provider }
  isDrawerOpen.value = true
}

const openDetail = provider => {
  router.push({ name: 'purchases-providers-id', params: { id: provider.id } })
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    const { id, ...payload } = formData
    if (id) {
      await providersStore.updateProvider(id, payload)
      notify.success('Proveedor actualizado correctamente')
    }
    else {
      await providersStore.createProvider(payload)
      notify.success('Proveedor creado correctamente')
    }
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeProvider = async provider => {
  if (!await confirmDelete({ entity: 'proveedor', name: provider.business_name }))
    return

  deletingId.value = provider.id
  try {
    await providersStore.deleteProvider(provider.id)
    notify.success('Proveedor eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el proveedor.' })
  }
  finally {
    deletingId.value = null
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
        table-id="providers"
        :headers="headers"
        :items="providersStore.items"
        :total-items="providersStore.meta.total"
        :loading="providersStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Distribuidora Andina o 20123456789"
        create-label="Nuevo proveedor"
        :creatable="can('providers.create')"
        @refresh="table.reload"
        @create="openCreateDrawer"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.is_active"
            :items="STATUS_OPTIONS"
            label="Estado"
            placeholder="Activo"
            density="compact"
            clearable
          />
        </template>

        <template #item.document_number="{ item }">
          <div class="d-flex align-center gap-1">
            <VChip
              size="x-small"
              variant="outlined"
              color="secondary"
            >
              {{ item.document_type }}
            </VChip>
            <span class="font-weight-medium font-monospace">{{ item.document_number }}</span>
          </div>
        </template>

        <template #item.business_name="{ item }">
          <div class="d-flex flex-column py-1">
            <span class="font-weight-medium text-body-1 text-high-emphasis">{{ item.business_name }}</span>
            <span
              v-if="item.trade_name"
              class="text-caption text-medium-emphasis"
            >{{ item.trade_name }}</span>
          </div>
        </template>

        <template #item.primary_contact="{ item }">
          <span v-if="item.primary_contact">{{ item.primary_contact.name }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <template #item.phone="{ item }">
          <span>{{ item.phone || item.primary_contact?.phone || '—' }}</span>
        </template>

        <template #item.credit_days="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="item.credit_days > 0 ? 'info' : 'secondary'"
          >
            {{ item.credit_days > 0 ? `Crédito ${item.credit_days} días` : 'Contado' }}
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'secondary'"
            size="small"
            class="text-uppercase"
          >
            {{ item.is_active ? 'Activo' : 'Inactivo' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              tooltip="Ver detalle"
              @click="openDetail(item)"
            />
            <RowActionButton
              v-if="can('providers.update')"
              action="edit"
              tooltip="Editar proveedor"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('providers.delete')"
              action="delete"
              tooltip="Eliminar proveedor"
              :loading="deletingId === item.id"
              @click="removeProvider(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <ProviderFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :provider-data="selectedProvider"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </div>
</template>
