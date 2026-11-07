<script setup>
import { computed, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import PageHeader from '@/components/common/PageHeader.vue'
import CategoriesTab from '@/modules/inventory/components/settings/CategoriesTab.vue'
import BrandsTab from '@/modules/inventory/components/settings/BrandsTab.vue'
import UnitsTab from '@/modules/inventory/components/settings/UnitsTab.vue'
import WarehousesTab from '@/modules/inventory/components/settings/WarehousesTab.vue'
import WarehouseReasonsTab from '@/modules/inventory/components/settings/WarehouseReasonsTab.vue'

definePage({
  meta: {
    // Basta con cualquiera de los permisos de ver: cada pestaña se muestra con el suyo.
    action: ['categories.view', 'brands.view', 'units.view', 'warehouses.view', 'warehouse_reasons.view'],
    title: 'Configuración de Inventario',
    icon: 'tabler-adjustments',
    description: 'Gestión de maestros de inventario: categorías, marcas, unidades, almacenes y motivos.',
  },
})

const { can } = useAcl()

const availableTabs = computed(() => {
  const tabs = []
  if (can('categories.view'))
    tabs.push({ id: 'categories', label: 'Categorías', icon: 'tabler-folder' })
  if (can('brands.view'))
    tabs.push({ id: 'brands', label: 'Marcas', icon: 'tabler-tags' })
  if (can('units.view'))
    tabs.push({ id: 'units', label: 'Unidades', icon: 'tabler-scale' })
  if (can('warehouses.view'))
    tabs.push({ id: 'warehouses', label: 'Almacenes', icon: 'tabler-building-warehouse' })
  if (can('warehouse_reasons.view'))
    tabs.push({ id: 'warehouse_reasons', label: 'Motivos', icon: 'tabler-arrows-left-right' })

  return tabs
})

const currentTab = ref(null)

// La pestaña inicial es la primera que el usuario puede ver.
watch(availableTabs, tabs => {
  if (!tabs.some(tab => tab.id === currentTab.value))
    currentTab.value = tabs[0]?.id ?? null
}, { immediate: true })
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VTabs
        v-model="currentTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          v-for="tab in availableTabs"
          :key="tab.id"
          :value="tab.id"
        >
          <VIcon
            start
            :icon="tab.icon"
          />
          {{ tab.label }}
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="currentTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <VWindowItem
          v-if="can('categories.view')"
          value="categories"
        >
          <CategoriesTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('brands.view')"
          value="brands"
        >
          <BrandsTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('units.view')"
          value="units"
        >
          <UnitsTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('warehouses.view')"
          value="warehouses"
        >
          <WarehousesTab />
        </VWindowItem>

        <VWindowItem
          v-if="can('warehouse_reasons.view')"
          value="warehouse_reasons"
        >
          <WarehouseReasonsTab />
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Destino de los drawers de las pestañas (ver SETTINGS_DRAWERS_TARGET) -->
    <div id="inventory-settings-drawers" />
  </div>
</template>
