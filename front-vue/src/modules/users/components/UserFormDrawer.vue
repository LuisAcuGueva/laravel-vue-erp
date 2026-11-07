<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { branchesApi } from '@/modules/maintenance/services/branchesApi'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  userData: {
    type: Object,
    default: null,
  },
  availableRoles: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
  serverErrors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { can } = useAcl()
const isEditing = computed(() => Boolean(props.userData?.id))

const defaultForm = () => ({
  name: '',
  email: '',
  password: '',
  status: 'active',
  roles: [],
  'branch_ids': [],
  'default_branch_id': null,
})

const form = ref(defaultForm())
const errorMessage = ref(null)
const availableBranches = ref([])

const loadBranchOptions = async () => {
  try {
    const res = await branchesApi.options()

    availableBranches.value = res?.data || []
  }
  catch {
    availableBranches.value = []
  }
}

onMounted(() => {
  loadBranchOptions()
})

const defaultBranchOptions = computed(() => {
  const selectedIds = form.value['branch_ids'] || []

  return availableBranches.value.filter(b => selectedIds.includes(b.id))
})

watch(() => form.value['branch_ids'], newBranchIds => {
  if (!Array.isArray(newBranchIds) || newBranchIds.length === 0) {
    form.value['default_branch_id'] = null
  }
  else if (!newBranchIds.includes(form.value['default_branch_id'])) {
    form.value['default_branch_id'] = newBranchIds[0]
  }
})

const roleSelectItems = computed(() => {
  return (props.availableRoles || []).map(r => {
    if (typeof r === 'object' && r !== null) {
      return {
        title: r.name || r.title || r.value,
        value: r.name || r.value || r.title,
      }
    }

    return {
      title: String(r),
      value: String(r),
    }
  })
})

watch(() => props.userData, val => {
  if (val) {
    const userRoles = Array.isArray(val.roles) ? [...val.roles] : []
    const userBranches = Array.isArray(val.branches) ? [...val.branches] : []
    const branchIds = userBranches.map(b => b.id)
    const defaultBranch = userBranches.find(b => b.is_default)

    form.value = {
      name: val.name || '',
      email: val.email || '',
      password: '',
      status: val.status || 'active',
      roles: userRoles,
      'branch_ids': branchIds,
      'default_branch_id': defaultBranch?.id || (branchIds[0] ?? null),
    }
  }
  else {
    form.value = defaultForm()
  }
  errorMessage.value = null
}, { immediate: true })

watch(() => props.isDrawerOpen, open => {
  if (open) {
    loadBranchOptions()
  }
})

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = defaultForm()
  errorMessage.value = null
}

const handleSubmit = () => {
  errorMessage.value = null

  if (!form.value.name || !form.value.name.trim()) {
    errorMessage.value = 'El nombre del usuario es obligatorio.'

    return
  }

  if (!form.value.email || !form.value.email.trim()) {
    errorMessage.value = 'El correo electrónico es obligatorio.'

    return
  }

  if (!isEditing.value && (!form.value.password || form.value.password.length < 8)) {
    errorMessage.value = 'La contraseña es obligatoria y debe tener al menos 8 caracteres.'

    return
  }

  if (isEditing.value && form.value.password && form.value.password.length < 8) {
    errorMessage.value = 'La nueva contraseña debe tener al menos 8 caracteres.'

    return
  }

  if (!form.value.roles || form.value.roles.length === 0) {
    errorMessage.value = 'Debe seleccionar al menos un rol para el usuario.'

    return
  }

  const payload = {
    name: form.value.name.trim(),
    email: form.value.email.trim(),
    status: form.value.status,
    roles: form.value.roles,
    'branch_ids': form.value['branch_ids'],
    'default_branch_id': form.value['default_branch_id'],
    id: props.userData?.id,
  }

  if (form.value.password && form.value.password.trim() !== '') {
    payload.password = form.value.password
  }

  emit('submit', payload)
}
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="420"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <!-- 👉 Header -->
    <DrawerHeader
      :title="isEditing ? 'Editar Usuario' : 'Nuevo Usuario'"
      icon="tabler-user"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errorMessage }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Nombre -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre completo *"
                placeholder="Juan Pérez"
                prepend-inner-icon="tabler-user"
                :error-messages="props.serverErrors?.name"
                required
              />
            </VCol>

            <!-- Correo -->
            <VCol cols="12">
              <VTextField
                v-model="form.email"
                label="Correo electrónico *"
                type="email"
                placeholder="juan@empresa.com"
                prepend-inner-icon="tabler-mail"
                :error-messages="props.serverErrors?.email"
                required
              />
            </VCol>

            <!-- Contraseña -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                :label="isEditing ? 'Contraseña' : 'Contraseña *'"
                type="password"
                placeholder="············"
                :hint="isEditing ? 'Déjala en blanco para no modificarla' : 'Mínimo 8 caracteres'"
                persistent-hint
                prepend-inner-icon="tabler-lock"
                :error-messages="props.serverErrors?.password"
                :required="!isEditing"
              />
            </VCol>

            <!-- Estado -->
            <VCol cols="12">
              <VSelect
                v-model="form.status"
                label="Estado *"
                :items="[
                  { title: 'Activo', value: 'active' },
                  { title: 'Inactivo', value: 'inactive' },
                ]"
                :error-messages="props.serverErrors?.status"
              />
            </VCol>

            <!-- Roles -->
            <VCol cols="12">
              <VSelect
                v-model="form.roles"
                label="Roles asignados"
                multiple
                chips
                closable-chips
                :items="roleSelectItems"
                item-title="title"
                item-value="value"
                :disabled="!can('roles.view')"
                :hint="!can('roles.view') ? 'No tienes permisos para consultar o modificar roles.' : undefined"
                :persistent-hint="!can('roles.view')"
                placeholder="vendedor"
                :error-messages="props.serverErrors?.roles"
              />
            </VCol>

            <!-- Sedes -->
            <VCol cols="12">
              <VAutocomplete
                v-model="form.branch_ids"
                label="Sedes asignadas"
                clearable
                multiple
                chips
                closable-chips
                :items="availableBranches"
                item-title="name"
                item-value="id"
                placeholder="Sede Central"
                :error-messages="props.serverErrors?.branch_ids"
              />
            </VCol>

            <!-- Sede por Defecto -->
            <VCol
              v-if="form.branch_ids && form.branch_ids.length > 0"
              cols="12"
            >
              <VSelect
                v-model="form.default_branch_id"
                label="Sede por defecto"
                clearable
                :items="defaultBranchOptions"
                item-title="name"
                item-value="id"
                placeholder="Sede Central"
                :error-messages="props.serverErrors?.default_branch_id"
              />
            </VCol>

            <!-- Acciones -->
            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Usuario' }}
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
