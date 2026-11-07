<script setup>
/* eslint-disable camelcase */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UbigeoSelect from '@/components/common/UbigeoSelect.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { clientsApi } from '@/modules/clients/services/clientsApi'

const props = defineProps({
  modelValue: {
    type: [String, Object, null],
    default: null,
  },
  initialItem: {
    type: Object,
    default: null,
  },
  label: {
    type: String,
    default: 'Cliente',
  },
  placeholder: {
    type: String,
    default: 'Escribe nombre o documento, p. ej. 20601234567',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  creatable: {
    type: Boolean,
    default: true,
  },
  requireRuc: {
    type: Boolean,
    default: false,
  },
  density: {
    type: String,
    default: 'comfortable',
  },
  errorMessages: {
    type: [String, Array],
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'selected', 'change'])

const { can } = useAcl()
const notify = useNotify()
const { errors: quickErrors, generalError: quickGeneralError, setErrors: setQuickErrors, clearErrors: clearQuickErrors } = useApiFormErrors()

const search = ref('')
const items = ref(props.initialItem ? [props.initialItem] : [])
const isLoading = ref(false)
const selectedItem = ref(props.initialItem || null)
const isQuickDialogOpen = ref(false)
const isSavingQuick = ref(false)

let debounceTimer = null

const formatTitle = client => {
  if (!client || typeof client !== 'object')
    return ''

  const doc = client.document_number ? `${client.document_type || 'DOC'} ${client.document_number} · ` : ''

  return `${doc}${client.name || ''}`
}

const filteredItems = computed(() => {
  if (!props.requireRuc)
    return items.value

  return items.value.filter(c => c.document_type === 'RUC')
})

const fetchOptions = async query => {
  isLoading.value = true
  try {
    const response = await clientsApi.options({ search: (query || '').trim() || undefined })
    const list = response?.data || []

    items.value = selectedItem.value && !list.some(it => it.id === selectedItem.value.id)
      ? [selectedItem.value, ...list]
      : list
  }
  catch {
    items.value = selectedItem.value ? [selectedItem.value] : []
  }
  finally {
    isLoading.value = false
  }
}

watch(search, query => {
  if (selectedItem.value && query === formatTitle(selectedItem.value))
    return

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchOptions(query), 250)
})

watch(() => props.initialItem, val => {
  if (val) {
    selectedItem.value = val
    items.value = [val]
  }
})

const onSelect = id => {
  selectedItem.value = items.value.find(it => it.id === id) || null
  emit('update:modelValue', id ?? null)
  emit('selected', selectedItem.value)
  emit('change', selectedItem.value)
}

// Quick Create Dialog
const quickForm = ref({
  client_type: 'company',
  document_type: 'RUC',
  document_number: '',
  name: '',
  address: '',
  ubigeo: null,
  default_price_type: 'retail',
})

const openQuickDialog = () => {
  quickForm.value = {
    client_type: props.requireRuc ? 'company' : 'person',
    document_type: props.requireRuc ? 'RUC' : 'DNI',
    document_number: '',
    name: '',
    address: '',
    ubigeo: null,
    default_price_type: 'retail',
  }
  clearQuickErrors()
  isQuickDialogOpen.value = true
}

const handleQuickSubmit = async () => {
  isSavingQuick.value = true
  clearQuickErrors()
  try {
    const res = await clientsApi.create(quickForm.value)
    const newClient = res?.data || res

    items.value = [newClient, ...items.value]
    onSelect(newClient.id)

    notify.success('Cliente creado correctamente')
    isQuickDialogOpen.value = false
  }
  catch (err) {
    setQuickErrors(err)
  }
  finally {
    isSavingQuick.value = false
  }
}

const onQuickClientTypeChange = val => {
  if (val === 'company') {
    quickForm.value.document_type = 'RUC'
  }
}

onBeforeUnmount(() => clearTimeout(debounceTimer))
</script>

<template>
  <div>
    <VAutocomplete
      v-model:search="search"
      :model-value="props.modelValue"
      :items="filteredItems"
      :item-title="formatTitle"
      item-value="id"
      :label="props.label"
      :placeholder="props.placeholder"
      :loading="isLoading"
      :disabled="props.disabled"
      :readonly="props.readonly"
      :clearable="props.clearable"
      :density="props.density"
      :error-messages="props.errorMessages"
      no-filter
      no-data-text="Sin resultados"
      @focus="fetchOptions(search)"
      @update:model-value="onSelect"
    >
      <template #append-inner>
        <VBtn
          v-if="props.creatable && can('clients.create') && !props.readonly && !props.disabled"
          icon="tabler-plus"
          variant="text"
          size="x-small"
          color="primary"
          class="ms-1"
          title="Alta rápida de cliente"
          @click.stop="openQuickDialog"
        />
      </template>
    </VAutocomplete>

    <!-- Diálogo de Alta Rápida -->
    <VDialog
      v-model="isQuickDialogOpen"
      max-width="520"
    >
      <VCard>
        <VCardTitle class="pa-4">
          Alta Rápida de Cliente
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-4">
          <VAlert
            v-if="quickGeneralError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ quickGeneralError }}
          </VAlert>

          <VForm @submit.prevent="handleQuickSubmit">
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="quickForm.client_type"
                  :items="[
                    { title: 'Persona Natural', value: 'person' },
                    { title: 'Persona Jurídica (Empresa)', value: 'company' },
                  ]"
                  label="Tipo de cliente *"
                  placeholder="Seleccione"
                  :error-messages="quickErrors.client_type"
                  @update:model-value="onQuickClientTypeChange"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="quickForm.document_type"
                  :items="props.requireRuc ? [{ title: 'RUC', value: 'RUC' }] : [
                    { title: 'DNI', value: 'DNI' },
                    { title: 'RUC', value: 'RUC' },
                    { title: 'Carné Extranjería', value: 'CE' },
                    { title: 'Pasaporte', value: 'PASSPORT' },
                  ]"
                  label="Tipo doc. *"
                  placeholder="RUC / DNI"
                  :disabled="quickForm.client_type === 'company' || props.requireRuc"
                  :error-messages="quickErrors.document_type"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="quickForm.document_number"
                  label="Número de documento *"
                  placeholder="Ej. 20601234567 o 45678912"
                  :error-messages="quickErrors.document_number"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="quickForm.name"
                  label="Nombre o razón social *"
                  placeholder="Ej. Comercial Andina S.A.C."
                  :error-messages="quickErrors.name"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="quickForm.address"
                  label="Dirección"
                  placeholder="Ej. Av. Los Próceres 123"
                  :error-messages="quickErrors.address"
                />
              </VCol>

              <VCol cols="12">
                <UbigeoSelect
                  v-model="quickForm.ubigeo"
                  :error-messages="quickErrors.ubigeo"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-4">
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSavingQuick"
                @click="isQuickDialogOpen = false"
              >
                Cancelar
              </VBtn>
              <VBtn
                type="submit"
                color="primary"
                :loading="isSavingQuick"
              >
                Crear y Seleccionar
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>
