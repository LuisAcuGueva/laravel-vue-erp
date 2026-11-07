<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import UbigeoSelect from '@/components/common/UbigeoSelect.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import ClientContactsTab from '@/modules/clients/components/ClientContactsTab.vue'
import ClientCreditCard from '@/modules/clients/components/ClientCreditCard.vue'
import ClientQuotationsTab from '@/modules/clients/components/ClientQuotationsTab.vue'
import ClientSalesTab from '@/modules/clients/components/ClientSalesTab.vue'
import ClientStatementTab from '@/modules/clients/components/ClientStatementTab.vue'
import { clientsApi } from '@/modules/clients/services/clientsApi'

definePage({
  meta: {
    action: 'clients.view',
    title: 'Ficha del Cliente',
    icon: 'tabler-users',
    description: 'Información detallada, contactos y condiciones de crédito del cliente',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation('/clients')

const clientId = route.params.id
const client = ref(null)
const isLoading = ref(true)
const isSaving = ref(false)
const activeTab = ref('general')

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const generalForm = ref({
  client_type: 'person',
  document_type: 'DNI',
  document_number: '',
  name: '',
  trade_name: '',
  representative_name: '',
  representative_document: '',
  email: '',
  phone: '',
  address: '',
  ubigeo: null,
  default_price_type: 'retail',
  description: '',
  is_active: true,
})

const priceTypeOptions = [
  { title: 'Minorista', value: 'retail' },
  { title: 'Mayorista', value: 'wholesale' },
]

const loadClient = async () => {
  try {
    const res = await clientsApi.get(clientId)

    client.value = res?.data || null
    if (client.value) {
      generalForm.value = {
        client_type: client.value.client_type || 'person',
        document_type: client.value.document_type || 'DNI',
        document_number: client.value.document_number || '',
        name: client.value.name || '',
        trade_name: client.value.trade_name || '',
        representative_name: client.value.representative_name || '',
        representative_document: client.value.representative_document || '',
        email: client.value.email || '',
        phone: client.value.phone || '',
        address: client.value.address || '',
        ubigeo: client.value.ubigeo || null,
        default_price_type: client.value.default_price_type || 'retail',
        description: client.value.description || '',
        is_active: client.value.is_active !== undefined ? Boolean(client.value.is_active) : true,
      }
    }
  }
  catch {
    notify.error('No se pudo cargar la información del cliente')
    router.push('/clients')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadClient)

const handleSaveGeneral = async () => {
  isSaving.value = true
  clearErrors()
  try {
    await clientsApi.update(clientId, generalForm.value)
    notify.success('Cliente actualizado correctamente')
    await loadClient()
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="client-detail-page">
    <VCard>
      <VCardText>
        <PageHeader
          :title="client?.name || 'Cargando cliente...'"
          :description="client ? `${client.document_type || ''} ${client.document_number || ''} ${client.trade_name ? '· ' + client.trade_name : ''}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="client?.is_system"
              color="warning"
              size="small"
              variant="tonal"
            >
              Cliente del Sistema
            </VChip>
            <VChip
              v-if="client"
              :color="client.client_type === 'company' ? 'primary' : 'info'"
              size="small"
              variant="tonal"
            >
              {{ client.client_type === 'company' ? 'Empresa' : 'Persona' }}
            </VChip>
            <VChip
              v-if="client"
              :color="client.is_active ? 'success' : 'secondary'"
              size="small"
              variant="tonal"
            >
              {{ client.is_active ? 'Activo' : 'Inactivo' }}
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
        <VTab value="general">
          <VIcon
            icon="tabler-info-circle"
            class="me-2"
          />
          General
        </VTab>
        <VTab value="contacts">
          <VIcon
            icon="tabler-address-book"
            class="me-2"
          />
          Contactos ({{ client?.contacts?.length || 0 }})
        </VTab>
        <VTab value="credit">
          <VIcon
            icon="tabler-credit-card"
            class="me-2"
          />
          Crédito
        </VTab>
        <VTab
          v-if="can('quotations.view')"
          value="quotations"
        >
          <VIcon
            icon="tabler-file-dollar"
            class="me-2"
          />
          Cotizaciones
        </VTab>
        <VTab
          v-if="can('sales.view')"
          value="sales"
        >
          <VIcon
            icon="tabler-receipt"
            class="me-2"
          />
          Ventas
        </VTab>
        <VTab
          v-if="can('receivables.view')"
          value="statement"
        >
          <VIcon
            icon="tabler-file-report"
            class="me-2"
          />
          Estado de cuenta
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow
        v-if="client"
        v-model="activeTab"
        class="disable-tab-transition"
      >
        <!-- Pestaña: General -->
        <VWindowItem value="general">
          <VCardText class="pa-6">
            <VAlert
              v-if="generalError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ generalError }}
            </VAlert>

            <VForm @submit.prevent="handleSaveGeneral">
              <VRow>
                <!-- Identificación -->
                <VCol cols="12">
                  <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                    Identificación
                  </span>
                </VCol>

                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    :model-value="client.client_type === 'company' ? 'Persona Jurídica (Empresa)' : 'Persona Natural'"
                    label="Tipo de cliente"
                    placeholder="Tipo de cliente"
                    readonly
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    :model-value="client.document_type || '—'"
                    label="Tipo de documento"
                    placeholder="RUC / DNI"
                    readonly
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model="generalForm.document_number"
                    label="Número de documento *"
                    placeholder="Ej. 20601234567"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.document_number"
                    @update:model-value="clearFieldError('document_number')"
                  />
                </VCol>

                <!-- Datos Principales -->
                <VCol cols="12">
                  <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                    Datos Principales
                  </span>
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="generalForm.name"
                    :label="client.client_type === 'company' ? 'Razón social *' : 'Apellidos y nombres *'"
                    placeholder="Ej. Inversiones Andinas S.A.C."
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.name"
                    @update:model-value="clearFieldError('name')"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="generalForm.trade_name"
                    label="Nombre comercial"
                    placeholder="Ej. Andinas"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.trade_name"
                    @update:model-value="clearFieldError('trade_name')"
                  />
                </VCol>

                <template v-if="client.client_type === 'company'">
                  <VCol
                    cols="12"
                    sm="7"
                  >
                    <VTextField
                      v-model="generalForm.representative_name"
                      label="Representante legal"
                      placeholder="Ej. Carlos Mendoza Ramos"
                      :readonly="!can('clients.update') || client.is_system"
                      :error-messages="errors.representative_name"
                      @update:model-value="clearFieldError('representative_name')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="5"
                  >
                    <VTextField
                      v-model="generalForm.representative_document"
                      label="DNI Representante"
                      placeholder="Ej. 09876543"
                      :readonly="!can('clients.update') || client.is_system"
                      :error-messages="errors.representative_document"
                      @update:model-value="clearFieldError('representative_document')"
                    />
                  </VCol>
                </template>

                <!-- Contacto y Ubicación -->
                <VCol cols="12">
                  <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                    Contacto y Ubicación
                  </span>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="generalForm.phone"
                    label="Teléfono"
                    placeholder="Ej. 987654321 o 01-4455667"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.phone"
                    @update:model-value="clearFieldError('phone')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="generalForm.email"
                    label="Correo electrónico"
                    placeholder="Ej. compras@andinas.pe"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.email"
                    @update:model-value="clearFieldError('email')"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="generalForm.address"
                    label="Dirección fiscal / domicilio"
                    placeholder="Ej. Av. Los Próceres 123"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.address"
                    @update:model-value="clearFieldError('address')"
                  />
                </VCol>

                <VCol cols="12">
                  <UbigeoSelect
                    v-model="generalForm.ubigeo"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.ubigeo"
                    @update:model-value="clearFieldError('ubigeo')"
                  />
                </VCol>

                <!-- Comercial y Observaciones -->
                <VCol cols="12">
                  <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                    Comercial
                  </span>
                </VCol>

                <VCol cols="12">
                  <VSelect
                    v-model="generalForm.default_price_type"
                    :items="priceTypeOptions"
                    label="Precio por defecto *"
                    placeholder="Seleccione Minorista o Mayorista"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.default_price_type"
                    @update:model-value="clearFieldError('default_price_type')"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextarea
                    v-model="generalForm.description"
                    label="Observaciones"
                    placeholder="Ej. Horario de recepción o notas comerciales"
                    rows="3"
                    :readonly="!can('clients.update') || client.is_system"
                    :error-messages="errors.description"
                    @update:model-value="clearFieldError('description')"
                  />
                </VCol>

                <VCol cols="12">
                  <VSwitch
                    v-model="generalForm.is_active"
                    label="Cliente activo"
                    color="primary"
                    :readonly="!can('clients.update') || client.is_system"
                  />
                </VCol>

                <VCol
                  v-if="can('clients.update') && !client.is_system"
                  cols="12"
                  class="d-flex justify-end gap-3"
                >
                  <VBtn
                    type="submit"
                    color="primary"
                    :loading="isSaving"
                  >
                    Guardar Cambios
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VWindowItem>

        <!-- Pestaña: Contactos -->
        <VWindowItem value="contacts">
          <ClientContactsTab
            :client="client"
            @refresh="loadClient"
          />
        </VWindowItem>

        <!-- Pestaña: Crédito -->
        <VWindowItem value="credit">
          <ClientCreditCard
            :client="client"
            @updated="loadClient"
          />
        </VWindowItem>

        <!-- Pestaña: Cotizaciones -->
        <VWindowItem
          v-if="can('quotations.view')"
          value="quotations"
        >
          <ClientQuotationsTab :client="client" />
        </VWindowItem>

        <!-- Pestaña: Ventas -->
        <VWindowItem
          v-if="can('sales.view')"
          value="sales"
        >
          <ClientSalesTab :client="client" />
        </VWindowItem>

        <!-- Pestaña: Estado de Cuenta -->
        <VWindowItem
          v-if="can('receivables.view')"
          value="statement"
        >
          <VCardText>
            <ClientStatementTab :client="client" />
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>
