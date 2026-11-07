<script setup>
/* eslint-disable camelcase */
import { ref, watch } from 'vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { clientsApi } from '@/modules/clients/services/clientsApi'

const props = defineProps({
  client: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['refresh'])

const { can } = useAcl()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const rows = ref([])
const isSaving = ref(false)

const toRows = client => (client.contacts || []).map(c => ({
  id: c.id,
  name: c.name || '',
  position: c.position || '',
  phone: c.phone || '',
  email: c.email || '',
  is_primary: Boolean(c.is_primary),
}))

watch(() => props.client, client => {
  rows.value = toRows(client)
  clearErrors()
}, { immediate: true })

const addRow = () => {
  rows.value.push({
    id: null,
    name: '',
    position: '',
    phone: '',
    email: '',
    is_primary: rows.value.length === 0,
  })
}

const removeRow = index => {
  rows.value.splice(index, 1)
}

const setPrimary = (index, value) => {
  rows.value.forEach((row, i) => {
    row.is_primary = i === index ? Boolean(value) : false
  })
}

const save = async () => {
  isSaving.value = true
  clearErrors()
  try {
    await clientsApi.syncContacts(props.client.id, {
      contacts: rows.value.map(row => ({
        id: row.id,
        name: row.name.trim(),
        position: row.position?.trim() || null,
        phone: row.phone?.trim() || null,
        email: row.email?.trim() || null,
        is_primary: row.is_primary,
      })),
    })
    notify.success('Contactos actualizados correctamente')
    emit('refresh')
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
  <VCardText>
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VTable
      density="comfortable"
      class="client-editable-table"
    >
      <thead>
        <tr>
          <th>NOMBRE *</th>
          <th>CARGO</th>
          <th>TELÉFONO</th>
          <th>CORREO</th>
          <th class="text-center">
            PRINCIPAL
          </th>
          <th
            v-if="can('clients.update') && !props.client.is_system"
            class="text-center"
          >
            ACCIONES
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!rows.length">
          <td
            colspan="6"
            class="text-center text-medium-emphasis py-6"
          >
            Aún no hay personas de contacto registradas.
          </td>
        </tr>
        <tr
          v-for="(row, index) in rows"
          :key="row.id || `new-${index}`"
        >
          <td>
            <VTextField
              v-model="row.name"
              label="Nombre *"
              placeholder="Ej. Ana Gómez"
              density="compact"
              maxlength="150"
              :readonly="!can('clients.update') || props.client.is_system"
              :error-messages="errors[`contacts.${index}.name`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.position"
              label="Cargo"
              placeholder="Ej. Jefa de compras"
              density="compact"
              maxlength="100"
              :readonly="!can('clients.update') || props.client.is_system"
            />
          </td>
          <td>
            <VTextField
              v-model="row.phone"
              label="Teléfono"
              placeholder="Ej. 987654321"
              density="compact"
              maxlength="30"
              :readonly="!can('clients.update') || props.client.is_system"
              :error-messages="errors[`contacts.${index}.phone`]"
            />
          </td>
          <td>
            <VTextField
              v-model="row.email"
              label="Correo"
              placeholder="Ej. ana@cliente.pe"
              density="compact"
              maxlength="150"
              :readonly="!can('clients.update') || props.client.is_system"
              :error-messages="errors[`contacts.${index}.email`]"
            />
          </td>
          <td class="text-center">
            <VSwitch
              :model-value="row.is_primary"
              density="compact"
              hide-details
              color="primary"
              :readonly="!can('clients.update') || props.client.is_system"
              @update:model-value="setPrimary(index, $event)"
            />
          </td>
          <td
            v-if="can('clients.update') && !props.client.is_system"
            class="text-center"
          >
            <RowActionButton
              action="delete"
              tooltip="Quitar contacto"
              @click="removeRow(index)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-if="can('clients.update') && !props.client.is_system"
      class="d-flex flex-wrap gap-4 mt-4"
    >
      <VBtn
        variant="tonal"
        prepend-icon="tabler-plus"
        :disabled="rows.length >= 20"
        @click="addRow"
      >
        Agregar contacto
      </VBtn>
      <VBtn
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="isSaving"
        @click="save"
      >
        Guardar contactos
      </VBtn>
    </div>
  </VCardText>
</template>
