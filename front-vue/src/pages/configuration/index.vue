<script setup>
import { ref } from 'vue'
import CompanyForm from '@/modules/configuration/components/CompanyForm.vue'
import ParametersTable from '@/modules/configuration/components/ParametersTable.vue'
import PageHeader from '@/components/common/PageHeader.vue'

definePage({
  meta: {
    action: 'configuration.view',
    title: 'Configuración',
    icon: 'tabler-settings',
    description: 'Datos de la empresa y parámetros de negocio del sistema',
  },
})

const currentTab = ref('company')
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <VTabs
      v-model="currentTab"
      class="v-tabs-pill mb-6"
    >
      <VTab value="company">
        <VIcon
          start
          icon="tabler-building"
        />
        Empresa
      </VTab>

      <VTab value="parameters">
        <VIcon
          start
          icon="tabler-adjustments-horizontal"
        />
        Parámetros
      </VTab>
    </VTabs>

    <VWindow
      v-model="currentTab"
      :touch="false"
      class="disable-tab-transition"
    >
      <VWindowItem value="company">
        <CompanyForm />
      </VWindowItem>

      <VWindowItem value="parameters">
        <ParametersTable />
      </VWindowItem>
    </VWindow>
  </div>
</template>
