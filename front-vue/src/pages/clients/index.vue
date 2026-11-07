<script setup>
/* eslint-disable camelcase */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ClientFormDrawer from '@/modules/clients/components/ClientFormDrawer.vue'
import { useClientsStore } from '@/modules/clients/stores/clientsStore'
import { formatAmount } from '@/utils/documentTotals'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'clients.view',
    title: 'Clientes',
    icon: 'tabler-users',
    description: 'Gestión de la cartera de clientes de la empresa',
  },
})

const router = useRouter()
const clientsStore = useClientsStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedClient = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const clientTypeFilterOptions = [
  { title: 'Persona Natural', value: 'person' },
  { title: 'Persona Jurídica', value: 'company' },
]

const table = useServerTable({
  fetch: params => clientsStore.fetchClients(params),
  filters: {
    client_type: null,
    is_active: null,
  },
})

const headers = [
  { title: 'TIPO', key: 'client_type', sortable: false, width: 120 },
  { title: 'DOCUMENTO', key: 'document_number', sortable: false, width: 160 },
  { title: 'CLIENTE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'TELÉFONO', key: 'phone', sortable: false, width: 130 },
  { title: 'CORREO', key: 'email', sortable: false },
  { title: 'CRÉDITO', key: 'credit_limit', sortable: false, width: 130 },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center', width: 110 },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center', width: 120 },
]

const openCreateDrawer = () => {
  selectedClient.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = client => {
  selectedClient.value = { ...client }
  isDrawerOpen.value = true
}

const openDetail = client => {
  router.push(`/clients/${client.id}`)
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await clientsStore.updateClient(formData.id, formData)
      notify.success('Cliente actualizado correctamente')
    }
    else {
      await clientsStore.createClient(formData)
      notify.success('Cliente creado correctamente')
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

const removeClient = async client => {
  if (!await confirmDelete({ entity: 'cliente', name: client.name }))
    return

  deletingId.value = client.id
  try {
    await clientsStore.deleteClient(client.id)
    notify.success('Cliente eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el cliente.' })
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
        table-id="clients"
        :headers="headers"
        :items="clientsStore.items"
        :total-items="clientsStore.meta.total"
        :loading="clientsStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por documento, nombre o correo"
        create-label="Nuevo cliente"
        :creatable="can('clients.create')"
        :exportable="can('clients.export')"
        :exporting="table.exporting"
        @refresh="table.reload"
        @create="openCreateDrawer"
        @export="table.export('/v1/clients/export', 'clientes')"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.client_type"
            :items="clientTypeFilterOptions"
            label="Tipo de cliente"
            placeholder="Persona o Empresa"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.is_active"
            :items="STATUS_OPTIONS"
            label="Estado"
            placeholder="Activo"
            density="compact"
            clearable
          />
        </template>

        <!-- Columna: Tipo de Cliente -->
        <template #item.client_type="{ item }">
          <VChip
            size="small"
            :color="item.client_type === 'company' ? 'primary' : 'info'"
            variant="tonal"
          >
            {{ item.client_type === 'company' ? 'Empresa' : 'Persona' }}
          </VChip>
        </template>

        <!-- Columna: Documento -->
        <template #item.document_number="{ item }">
          <div
            v-if="item.document_number"
            class="d-flex align-center gap-1"
          >
            <VChip
              size="x-small"
              variant="outlined"
              color="secondary"
            >
              {{ item.document_type || 'DOC' }}
            </VChip>
            <span class="font-weight-medium font-monospace">{{ item.document_number }}</span>
          </div>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <!-- Columna: Cliente (Nombre + Nombre Comercial) -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-1">
            <div class="d-flex align-center gap-1">
              <span class="font-weight-medium text-high-emphasis">{{ item.name }}</span>
              <VChip
                v-if="item.is_system"
                size="x-small"
                color="warning"
                variant="tonal"
                class="ms-1"
              >
                Sistema
              </VChip>
            </div>
            <span
              v-if="item.trade_name"
              class="text-caption text-medium-emphasis"
            >
              {{ item.trade_name }}
            </span>
          </div>
        </template>

        <!-- Columna: Teléfono -->
        <template #item.phone="{ item }">
          <span :class="item.phone ? 'text-body-2' : 'text-medium-emphasis'">
            {{ item.phone || '—' }}
          </span>
        </template>

        <!-- Columna: Correo -->
        <template #item.email="{ item }">
          <span :class="item.email ? 'text-body-2' : 'text-medium-emphasis'">
            {{ item.email || '—' }}
          </span>
        </template>

        <!-- Columna: Crédito -->
        <template #item.credit_limit="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">
              {{ formatAmount(item.credit_limit, 'S/') }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ item.credit_days > 0 ? `${item.credit_days} d.` : 'Contado' }}
            </span>
          </div>
        </template>

        <!-- Columna: Estado -->
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_active ? 'Activo' : 'Inactivo' }}
          </VChip>
        </template>

        <!-- Columna: Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <RowActionButton
              action="view"
              @click="openDetail(item)"
            />
            <RowActionButton
              v-if="can('clients.update') && !item.is_system"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('clients.delete') && !item.is_system"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeClient(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer de Creación/Edición -->
    <ClientFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :client-data="selectedClient"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </div>
</template>
