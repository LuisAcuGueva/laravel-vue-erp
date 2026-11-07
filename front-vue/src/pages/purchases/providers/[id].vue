<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import ProviderBankAccountsTab from '@/modules/purchases/components/providers/ProviderBankAccountsTab.vue'
import ProviderContactsTab from '@/modules/purchases/components/providers/ProviderContactsTab.vue'
import ProviderGeneralForm from '@/modules/purchases/components/providers/ProviderGeneralForm.vue'
import ProviderPricesTab from '@/modules/purchases/components/providers/ProviderPricesTab.vue'
import { providersApi } from '@/modules/purchases/services/providersApi'

definePage({
  meta: {
    action: 'providers.view',
    title: 'Ficha del Proveedor',
    icon: 'tabler-truck-delivery',
    description: 'Datos generales, contactos, cuentas bancarias y precios del proveedor.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation({ name: 'purchases-providers' })

const providerId = route.params.id
const provider = ref(null)
const isLoading = ref(true)
const isSaving = ref(false)
const activeTab = ref('general')
const generalFormRef = ref(null)

const loadProvider = async () => {
  try {
    const res = await providersApi.get(providerId)

    provider.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar el proveedor')
    router.push({ name: 'purchases-providers' })
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadProvider)

const handleSaveGeneral = async payload => {
  isSaving.value = true
  generalFormRef.value?.clearErrors()
  try {
    await providersApi.update(providerId, payload)
    notify.success('Proveedor actualizado correctamente')
    await loadProvider()
  }
  catch (err) {
    generalFormRef.value?.setErrors(err)
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="provider-detail-page">
    <VCard>
      <VCardText>
        <PageHeader
          :title="provider?.business_name || 'Cargando proveedor...'"
          :description="provider ? `${provider.document_type} ${provider.document_number}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="provider"
              :color="provider.is_active ? 'success' : 'secondary'"
              size="small"
              class="text-uppercase"
            >
              {{ provider.is_active ? 'Activo' : 'Inactivo' }}
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
          General
        </VTab>
        <VTab
          value="contacts"
          prepend-icon="tabler-address-book"
        >
          Contactos
        </VTab>
        <VTab
          value="accounts"
          prepend-icon="tabler-building-bank"
        >
          Cuentas bancarias
        </VTab>
        <VTab
          value="prices"
          prepend-icon="tabler-currency-dollar"
        >
          Precios
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-model="activeTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <VWindowItem value="general">
          <VCardText>
            <ProviderGeneralForm
              v-if="provider"
              ref="generalFormRef"
              :provider="provider"
              :is-submitting="isSaving"
              :readonly="!can('providers.update')"
              submit-label="Guardar Cambios"
              @submit="handleSaveGeneral"
            />
          </VCardText>
        </VWindowItem>

        <VWindowItem value="contacts">
          <ProviderContactsTab
            v-if="provider"
            :provider="provider"
            @refresh="loadProvider"
          />
        </VWindowItem>

        <VWindowItem value="accounts">
          <ProviderBankAccountsTab
            v-if="provider"
            :provider="provider"
            @refresh="loadProvider"
          />
        </VWindowItem>

        <VWindowItem value="prices">
          <ProviderPricesTab
            v-if="provider"
            :provider="provider"
          />
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>
