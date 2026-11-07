<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import UbigeoSelect from '@/components/common/UbigeoSelect.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  clientData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.clientData?.id))

const clientTypeOptions = [
  { title: 'Persona Natural', value: 'person' },
  { title: 'Persona Jurídica (Empresa)', value: 'company' },
]

const documentTypeOptions = [
  { title: 'DNI', value: 'DNI' },
  { title: 'RUC', value: 'RUC' },
  { title: 'Carné de Extranjería (CE)', value: 'CE' },
  { title: 'Pasaporte', value: 'PASSPORT' },
  { title: 'Otro', value: 'OTHER' },
]

const priceTypeOptions = [
  { title: 'Minorista', value: 'retail' },
  { title: 'Mayorista', value: 'wholesale' },
]

const defaultForm = () => ({
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

const form = ref(defaultForm())

watch(
  () => form.value.client_type,
  newType => {
    if (newType === 'company') {
      form.value.document_type = 'RUC'
    }
  },
)

watch(
  () => props.clientData,
  val => {
    if (val) {
      form.value = {
        client_type: val.client_type || 'person',
        document_type: val.document_type || 'DNI',
        document_number: val.document_number || '',
        name: val.name || '',
        trade_name: val.trade_name || '',
        representative_name: val.representative_name || '',
        representative_document: val.representative_document || '',
        email: val.email || '',
        phone: val.phone || '',
        address: val.address || '',
        ubigeo: val.ubigeo || null,
        default_price_type: val.default_price_type || 'retail',
        description: val.description || '',
        is_active: val.is_active !== undefined ? Boolean(val.is_active) : true,
      }
    }
    else {
      form.value = defaultForm()
    }
    clearErrors()
  },
  { immediate: true },
)

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = defaultForm()
  clearErrors()
}

const handleSubmit = () => {
  if (!form.value.name || !form.value.name.trim()) {
    errors.value.name = 'El nombre o razón social es obligatorio.'
    
    return
  }

  if (!form.value.document_number || !form.value.document_number.trim()) {
    errors.value.document_number = 'El número de documento es obligatorio.'
    
    return
  }

  emit('submit', {
    ...form.value,
    id: props.clientData?.id,
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="480"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <!-- 👉 Header -->
    <DrawerHeader
      :title="isEditing ? 'Editar Cliente' : 'Nuevo Cliente'"
      icon="tabler-users"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText class="pb-6">
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
            <!-- SECCIÓN: Identificación -->
            <VCol cols="12">
              <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                Identificación
              </span>
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.client_type"
                :items="clientTypeOptions"
                label="Tipo de cliente *"
                placeholder="Seleccione persona o empresa"
                :error-messages="errors.client_type"
                @update:model-value="clearFieldError('client_type')"
              />
            </VCol>

            <VCol
              cols="12"
              sm="5"
            >
              <VSelect
                v-model="form.document_type"
                :items="documentTypeOptions"
                label="Tipo doc. *"
                placeholder="RUC / DNI"
                :disabled="form.client_type === 'company'"
                :error-messages="errors.document_type"
                @update:model-value="clearFieldError('document_type')"
              />
            </VCol>

            <VCol
              cols="12"
              sm="7"
            >
              <VTextField
                v-model="form.document_number"
                label="Número de documento *"
                placeholder="Ej. 20601234567 o 45678912"
                :error-messages="errors.document_number"
                @update:model-value="clearFieldError('document_number')"
              />
            </VCol>

            <!-- SECCIÓN: Datos Principales -->
            <VCol cols="12">
              <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                Datos Principales
              </span>
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                :label="form.client_type === 'company' ? 'Razón social *' : 'Apellidos y nombres *'"
                placeholder="Ej. Inversiones Andinas S.A.C."
                :error-messages="errors.name"
                @update:model-value="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.trade_name"
                label="Nombre comercial"
                placeholder="Ej. Andinas"
                :error-messages="errors.trade_name"
                @update:model-value="clearFieldError('trade_name')"
              />
            </VCol>

            <template v-if="form.client_type === 'company'">
              <VCol
                cols="12"
                sm="7"
              >
                <VTextField
                  v-model="form.representative_name"
                  label="Representante legal"
                  placeholder="Ej. Carlos Mendoza Ramos"
                  :error-messages="errors.representative_name"
                  @update:model-value="clearFieldError('representative_name')"
                />
              </VCol>
              <VCol
                cols="12"
                sm="5"
              >
                <VTextField
                  v-model="form.representative_document"
                  label="DNI Representante"
                  placeholder="Ej. 09876543"
                  :error-messages="errors.representative_document"
                  @update:model-value="clearFieldError('representative_document')"
                />
              </VCol>
            </template>

            <!-- SECCIÓN: Contacto y Ubicación -->
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
                v-model="form.phone"
                label="Teléfono"
                placeholder="Ej. 987654321 o 01-4455667"
                :error-messages="errors.phone"
                @update:model-value="clearFieldError('phone')"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.email"
                label="Correo electrónico"
                placeholder="Ej. compras@andinas.pe"
                :error-messages="errors.email"
                @update:model-value="clearFieldError('email')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.address"
                label="Dirección fiscal / domicilio"
                placeholder="Ej. Av. Los Próceres 123"
                :error-messages="errors.address"
                @update:model-value="clearFieldError('address')"
              />
            </VCol>

            <VCol cols="12">
              <UbigeoSelect
                v-model="form.ubigeo"
                :error-messages="errors.ubigeo"
                @update:model-value="clearFieldError('ubigeo')"
              />
            </VCol>

            <!-- SECCIÓN: Comercial y Observaciones -->
            <VCol cols="12">
              <span class="text-caption text-uppercase font-weight-medium text-medium-emphasis">
                Condiciones Comerciales
              </span>
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.default_price_type"
                :items="priceTypeOptions"
                label="Precio por defecto *"
                placeholder="Seleccione Minorista o Mayorista"
                :error-messages="errors.default_price_type"
                @update:model-value="clearFieldError('default_price_type')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Observaciones"
                placeholder="Ej. Horario de recepción o notas comerciales"
                :error-messages="errors.description"
                rows="3"
                @update:model-value="clearFieldError('description')"
              />
            </VCol>

            <VCol
              v-if="isEditing"
              cols="12"
            >
              <VSwitch
                v-model="form.is_active"
                label="Cliente activo"
                color="primary"
              />
            </VCol>

            <!-- Botones de Acción -->
            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Cliente' }}
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="props.isSubmitting"
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
