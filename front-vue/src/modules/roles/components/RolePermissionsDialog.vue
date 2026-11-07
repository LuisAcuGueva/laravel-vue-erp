<script setup>
import { computed, defineEmits, defineProps, ref, watch } from 'vue'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  role: {
    type: Object,
    default: null,
  },
  availablePermissions: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'save'])

const searchQuery = ref('')
const selectedSlugs = ref(new Set())

watch(
  () => [props.role, props.isDialogOpen],
  () => {
    if (props.role && props.isDialogOpen) {
      const initial = Array.isArray(props.role.permissions) ? props.role.permissions : []

      selectedSlugs.value = new Set(initial)
      searchQuery.value = ''
    }
    else if (!props.isDialogOpen) {
      selectedSlugs.value = new Set()
      searchQuery.value = ''
    }
  },
  { immediate: true },
)

const handleClose = () => {
  emit('update:isDialogOpen', false)
}

const normalizedPermissions = computed(() => {
  return props.availablePermissions.map(p => {
    if (typeof p === 'string') {
      const parts = p.split('.')
      const module = parts.length > 1 ? parts[0] : 'GENERAL'

      return {
        id: p,
        slug: p,
        name: p,
        module: module.toUpperCase(),
        description: '',
      }
    }

    return {
      id: p.id || p.slug,
      slug: p.slug || p.name,
      name: p.name || p.slug,
      module: (p.module || (p.slug ? p.slug.split('.')[0] : 'GENERAL')).toUpperCase(),
      description: p.description || '',
    }
  })
})

const permissionsMap = computed(() => {
  const map = new Map()

  normalizedPermissions.value.forEach(p => {
    map.set(p.slug, p)
  })

  return map
})

const unassignedFilteredList = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return normalizedPermissions.value.filter(p => {
    if (selectedSlugs.value.has(p.slug))
      return false
    if (!query)
      return true

    return (
      p.slug.toLowerCase().includes(query)
      || p.name.toLowerCase().includes(query)
      || p.module.toLowerCase().includes(query)
    )
  })
})

const groupedAvailablePermissions = computed(() => {
  const groups = {}

  unassignedFilteredList.value.forEach(p => {
    const mod = p.module || 'GENERAL'
    if (!groups[mod])
      groups[mod] = []

    groups[mod].push(p)
  })

  return groups
})

const assignedPermissionsList = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  const list = []

  selectedSlugs.value.forEach(slug => {
    const perm = permissionsMap.value.get(slug) || {
      id: slug,
      slug,
      name: slug,
      module: slug.includes('.') ? slug.split('.')[0].toUpperCase() : 'GENERAL',
      description: '',
    }

    if (
      !query
      || perm.slug.toLowerCase().includes(query)
      || perm.name.toLowerCase().includes(query)
      || perm.module.toLowerCase().includes(query)
    ) {
      list.push(perm)
    }
  })

  return list
})

const assignPermission = slug => {
  selectedSlugs.value = new Set([...selectedSlugs.value, slug])
}

const removePermission = slug => {
  const updated = new Set(selectedSlugs.value)

  updated.delete(slug)
  selectedSlugs.value = updated
}

const assignAllFiltered = () => {
  const newSet = new Set(selectedSlugs.value)

  unassignedFilteredList.value.forEach(p => newSet.add(p.slug))
  selectedSlugs.value = newSet
}

const removeAllAssigned = () => {
  selectedSlugs.value = new Set()
}

const handleSave = () => {
  if (!props.role)
    return

  emit('save', {
    roleId: props.role.id,
    permissions: Array.from(selectedSlugs.value),
  })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    :width="$vuetify.display.smAndDown ? 'auto' : 920"
    persistent
    @update:model-value="handleClose"
  >
    <DialogCloseBtn @click="handleClose" />

    <VCard class="manage-permissions-dialog-card pa-sm-7 pa-4">
      <!-- Header -->
      <VCardItem class="pa-0 mb-3">
        <VCardTitle class="text-h5 font-weight-semibold">
          Gestionar Permisos
        </VCardTitle>
        <VCardSubtitle class="text-body-2">
          Asigna o remueve permisos para definir los accesos del rol en el sistema.
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pa-0 overflow-hidden">
        <!-- Banner del Rol -->
        <div class="role-header-card d-flex align-center gap-3 pa-3 rounded mb-4">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              icon="tabler-key"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <h6 class="text-h6 font-weight-semibold mb-0">
              {{ props.role?.name || 'Rol' }}
            </h6>
            <div class="d-flex align-center gap-2 mt-1">
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
                class="font-weight-medium"
              >
                {{ selectedSlugs.size }} asignados
              </VChip>
              <span
                v-if="props.role?.description"
                class="text-caption text-medium-emphasis"
              >
                {{ props.role.description }}
              </span>
            </div>
          </div>
        </div>

        <!-- Buscador -->
        <VTextField
          v-model="searchQuery"
          label="Buscar permiso"
          placeholder="clients.view"
          prepend-inner-icon="tabler-search"
          clearable
          class="mb-4"
        />

        <!-- Transfer Grid (2 Columnas) -->
        <div class="d-flex flex-column flex-md-row gap-4 w-100">
          <!-- Columna: Disponibles -->
          <div class="permissions-column d-flex flex-column flex-1-1-0">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <span class="text-subtitle-2 font-weight-semibold text-high-emphasis">
                  Disponibles
                </span>
                <VChip
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ unassignedFilteredList.length }}
                </VChip>
              </div>

              <VBtn
                v-if="unassignedFilteredList.length > 0"
                variant="text"
                size="x-small"
                color="primary"
                class="px-1 text-capitalize"
                @click="assignAllFiltered"
              >
                Asignar todos
              </VBtn>
            </div>

            <!-- Panel Scrolleable -->
            <div class="permissions-scroll-container pa-3 rounded">
              <div
                v-if="Object.keys(groupedAvailablePermissions).length === 0"
                class="empty-state py-8 text-center"
              >
                <VAvatar
                  color="secondary"
                  variant="tonal"
                  size="44"
                  class="mb-2"
                >
                  <VIcon
                    icon="tabler-filter-off"
                    size="22"
                    class="text-disabled"
                  />
                </VAvatar>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  No hay permisos disponibles
                </p>
                <span
                  v-if="searchQuery"
                  class="text-caption text-disabled"
                >
                  Prueba cambiando el término de búsqueda
                </span>
              </div>

              <div
                v-for="(perms, moduleName) in groupedAvailablePermissions"
                :key="moduleName"
                class="module-group mb-3"
              >
                <div class="module-title text-caption font-weight-bold text-uppercase text-primary mb-2 px-1">
                  {{ moduleName }}
                </div>

                <div class="d-flex flex-column gap-2">
                  <div
                    v-for="perm in perms"
                    :key="perm.slug"
                    class="permission-item d-flex align-center justify-space-between pa-3 rounded"
                    @click="assignPermission(perm.slug)"
                  >
                    <div class="d-flex flex-column pe-2 overflow-hidden">
                      <div class="d-flex align-center mb-1">
                        <VChip
                          size="x-small"
                          color="secondary"
                          variant="tonal"
                          class="font-mono text-caption"
                        >
                          {{ perm.slug }}
                        </VChip>
                      </div>
                      <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                        {{ perm.name }}
                      </span>
                      <span
                        v-if="perm.description && perm.description !== perm.name"
                        class="text-caption text-medium-emphasis text-truncate mt-1"
                      >
                        {{ perm.description }}
                      </span>
                    </div>

                    <IconBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      title="Asignar permiso"
                      @click.stop="assignPermission(perm.slug)"
                    >
                      <VIcon
                        icon="tabler-plus"
                        size="18"
                      />
                    </IconBtn>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Columna: Asignados -->
          <div class="permissions-column d-flex flex-column flex-1-1-0">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <span class="text-subtitle-2 font-weight-semibold text-high-emphasis">
                  Asignados
                </span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ selectedSlugs.size }}
                </VChip>
              </div>

              <VBtn
                v-if="selectedSlugs.size > 0"
                variant="text"
                size="x-small"
                color="error"
                class="px-1 text-capitalize"
                @click="removeAllAssigned"
              >
                Remover todos
              </VBtn>
            </div>

            <!-- Panel Scrolleable -->
            <div class="permissions-scroll-container pa-3 rounded">
              <div
                v-if="selectedSlugs.size === 0"
                class="empty-state py-10 text-center"
              >
                <VAvatar
                  color="secondary"
                  variant="tonal"
                  size="50"
                  class="mb-2"
                >
                  <VIcon
                    icon="tabler-key"
                    size="26"
                    class="text-disabled"
                  />
                </VAvatar>
                <p class="text-body-1 font-weight-medium text-high-emphasis mb-1">
                  Sin permisos asignados
                </p>
                <span class="text-caption text-medium-emphasis">
                  Haz clic en un permiso de la izquierda para agregarlo
                </span>
              </div>

              <div
                v-else
                class="d-flex flex-column gap-2"
              >
                <div
                  v-for="perm in assignedPermissionsList"
                  :key="perm.slug"
                  class="permission-item assigned-item d-flex align-center justify-space-between pa-3 rounded"
                  @click="removePermission(perm.slug)"
                >
                  <div class="d-flex flex-column pe-2 overflow-hidden">
                    <div class="d-flex align-center mb-1">
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        class="font-mono text-caption"
                      >
                        {{ perm.slug }}
                      </VChip>
                    </div>
                    <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                      {{ perm.name }}
                    </span>
                    <span
                      v-if="perm.description && perm.description !== perm.name"
                      class="text-caption text-medium-emphasis text-truncate mt-1"
                    >
                      {{ perm.description }}
                    </span>
                  </div>

                  <IconBtn
                    size="small"
                    variant="tonal"
                    color="error"
                    title="Remover permiso"
                    @click.stop="removePermission(perm.slug)"
                  >
                    <VIcon
                      icon="tabler-minus"
                      size="18"
                    />
                  </IconBtn>
                </div>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <!-- Footer & Botones de Acción -->
      <VCardActions class="d-flex flex-wrap align-center justify-space-between pa-0 pt-4 mt-3">
        <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
          <VIcon
            icon="tabler-info-circle"
            size="16"
          />
          <span>Haz clic en un permiso o en los botones para transferirlos</span>
        </div>

        <div class="d-flex align-center gap-3">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="props.isSubmitting"
            @click="handleClose"
          >
            Cancelar
          </VBtn>

          <VBtn
            color="primary"
            :loading="props.isSubmitting"
            @click="handleSave"
          >
            Guardar Permisos
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.manage-permissions-dialog-card {
  overflow: hidden;
}

.role-header-card {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.permissions-column {
  min-inline-size: 0;
}

.permissions-scroll-container {
  block-size: 340px;
  max-block-size: 340px;
  overflow-y: auto;
  overflow-x: hidden;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-on-surface), 0.16) transparent;
}

.permissions-scroll-container::-webkit-scrollbar {
  inline-size: 6px;
}

.permissions-scroll-container::-webkit-scrollbar-button {
  display: none !important;
  inline-size: 0 !important;
  block-size: 0 !important;
}

.permissions-scroll-container::-webkit-scrollbar-track {
  background: transparent;
}

.permissions-scroll-container::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-on-surface), 0.16);
  border-radius: 4px;
}

.permissions-scroll-container::-webkit-scrollbar-thumb:hover {
  background: rgba(var(--v-theme-on-surface), 0.3);
}

.module-title {
  letter-spacing: 0.08em;
  font-size: 0.72rem;
}

.permission-item {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: all 0.16s ease-in-out;
}

.permission-item:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.15);
}

.permission-item.assigned-item:hover {
  border-color: rgb(var(--v-theme-error));
  box-shadow: 0 2px 8px rgba(var(--v-theme-error), 0.15);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-block-size: 280px;
}

.font-mono {
  font-family: monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
