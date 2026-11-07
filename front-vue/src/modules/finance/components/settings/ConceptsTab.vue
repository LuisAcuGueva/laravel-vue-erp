<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import ConceptFormDrawer from './ConceptFormDrawer.vue'
import { conceptsApi } from '../../services/financeSettingsApi'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { CONCEPT_DIRECTIONS, SETTINGS_DRAWERS_TARGET } from '../../utils/constants'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const financeStore = useFinanceSettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedConcept = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => conceptsApi.list(params),
  filters: {
    direction: null,
    'is_active': null,
    'has_account': null,
  },
})

const ACCOUNT_OPTIONS = [
  { title: 'Con cuenta', value: 1 },
  { title: 'Sin cuenta', value: 0 },
]

const health = computed(() => financeStore.health)
const canViewHealth = computed(() => can('finance_settings.view'))
const showHealth = computed(() => canViewHealth.value && health.value && health.value.status !== 'ok')
const hasUnassigned = computed(() => (health.value?.issues || []).some(issue => issue.code === 'concept_without_account'))

const refreshHealth = async () => {
  try {
    await financeStore.fetchHealth(true)
  }
  catch {
    // El diagnóstico es informativo: un fallo no bloquea el listado.
  }
}

const showWithoutAccount = () => {
  table.filters['has_account'] = 0
}

onMounted(refreshHealth)

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'DIRECCIÓN', key: 'direction', sortable: false, align: 'center' },
  { title: 'CUENTA CONTABLE', key: 'financial_account.name', sortable: false },
  { title: 'SISTEMA', key: 'is_system', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const getDirectionLabel = direction => {
  const value = direction?.value || direction
  const found = CONCEPT_DIRECTIONS.find(d => d.value === value)

  return found ? found.title : value
}

const getDirectionColor = direction => {
  const value = direction?.value || direction
  switch (value) {
  case 'income':
    return 'success'
  case 'expense':
    return 'error'
  case 'transfer':
    return 'info'
  default:
    return 'secondary'
  }
}

const openCreateDrawer = () => {
  selectedConcept.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = concept => {
  selectedConcept.value = { ...concept }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await conceptsApi.update(id, payload)
      notify.success('Concepto financiero actualizado correctamente')
    }
    else {
      await conceptsApi.create(payload)
      notify.success('Concepto financiero creado correctamente')
    }
    isDrawerOpen.value = false
    financeStore.invalidateConcepts()
    await table.reload()
    await refreshHealth()
  }
  catch (err) {
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeConcept = async concept => {
  if (concept.is_system) {
    await alert({ message: 'No se puede eliminar un concepto de sistema.' })

    return
  }

  if (!await confirmDelete({ entity: 'concepto', name: concept.name }))
    return

  deletingId.value = concept.id
  try {
    await conceptsApi.delete(concept.id)
    notify.success('Concepto eliminado correctamente')
    financeStore.invalidateConcepts()
    await table.reload()
    await refreshHealth()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el concepto.' })
  }
  finally {
    deletingId.value = null
  }
}
</script>

<template>
  <VAlert
    v-if="showHealth"
    :type="health.status === 'error' ? 'error' : 'warning'"
    variant="tonal"
    class="ma-4"
  >
    <div class="font-weight-medium mb-1">
      Revisa la configuración contable
    </div>
    <ul class="ps-4">
      <li
        v-for="issue in health.issues"
        :key="issue.code"
      >
        {{ issue.message }}
      </li>
    </ul>
    <template
      v-if="hasUnassigned"
      #append
    >
      <VBtn
        size="small"
        variant="tonal"
        @click="showWithoutAccount"
      >
        Ver sin cuenta
      </VBtn>
    </template>
  </VAlert>

  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="finance-concepts"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Cobro / Pago / Transferencia..."
    create-label="Nuevo concepto"
    :creatable="can('finance_settings.manage')"
    @refresh="table.reload"
    @create="openCreateDrawer"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.direction"
        :items="CONCEPT_DIRECTIONS"
        label="Dirección"
        placeholder="Ingreso"
        density="compact"
        clearable
      />

      <VSelect
        v-model="table.filters.has_account"
        :items="ACCOUNT_OPTIONS"
        label="Cuenta contable"
        placeholder="Sin cuenta"
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

    <template #item.code="{ item }">
      <div class="d-flex align-center gap-1">
        <span class="font-weight-medium">{{ item.code }}</span>
        <VTooltip
          v-if="item.is_system"
          text="Concepto del sistema (protegido)"
        >
          <template #activator="{ props: tooltipProps }">
            <VIcon
              v-bind="tooltipProps"
              size="16"
              icon="tabler-lock"
              class="text-disabled"
            />
          </template>
        </VTooltip>
      </div>
    </template>

    <template #item.direction="{ item }">
      <VChip
        size="small"
        variant="tonal"
        :color="getDirectionColor(item.direction)"
      >
        {{ getDirectionLabel(item.direction) }}
      </VChip>
    </template>

    <template #item.financial_account.name="{ item }">
      <span v-if="item.financial_account">
        {{ item.financial_account.code }} - {{ item.financial_account.name }}
      </span>
      <VChip
        v-else
        size="small"
        color="warning"
        variant="tonal"
      >
        Sin cuenta
      </VChip>
    </template>

    <template #item.is_system="{ item }">
      <VChip
        size="small"
        :color="item.is_system ? 'info' : 'secondary'"
      >
        {{ item.is_system ? 'Sistema' : 'Personalizado' }}
      </VChip>
    </template>

    <template #item.is_active="{ item }">
      <VChip
        size="small"
        :color="item.is_active ? 'success' : 'secondary'"
      >
        {{ item.is_active ? 'Activo' : 'Inactivo' }}
      </VChip>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-center gap-1">
        <RowActionButton
          v-if="can('finance_settings.manage')"
          action="edit"
          tooltip="Editar concepto"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('finance_settings.manage') && !item.is_system"
          action="delete"
          tooltip="Eliminar concepto"
          :loading="deletingId === item.id"
          @click="removeConcept(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <ConceptFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :concept-data="selectedConcept"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
