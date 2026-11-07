<script setup>
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const { branches, activeBranchId, activeBranch, setActiveBranch } = useAuth()

const handleSelectBranch = branchId => {
  if (branchId === activeBranchId.value)
    return

  setActiveBranch(branchId)

  if (router && typeof router.go === 'function') {
    router.go(0)
  }
  else if (typeof window !== 'undefined') {
    window.location.reload()
  }
}
</script>

<template>
  <div
    v-if="branches && branches.length > 1"
    class="branch-switcher me-2"
  >
    <VMenu
      offset="14px"
      location="bottom end"
    >
      <template #activator="{ props: menuProps }">
        <VBtn
          v-bind="menuProps"
          variant="tonal"
          color="primary"
          size="small"
          class="text-none px-3"
          aria-label="Seleccionar sede activa"
        >
          <VIcon
            start
            icon="tabler-building-store"
            size="18"
          />
          <span class="d-none d-sm-inline font-weight-medium text-truncate branch-switcher__title">
            {{ activeBranch?.name || 'Seleccionar sede' }}
          </span>
          <VIcon
            end
            icon="tabler-chevron-down"
            size="16"
          />
        </VBtn>
      </template>

      <VList
        density="compact"
        class="py-1"
        min-width="220"
      >
        <VListSubheader class="text-uppercase text-caption font-weight-bold">
          Cambiar de Sede
        </VListSubheader>

        <VListItem
          v-for="branch in branches"
          :key="branch.id"
          :active="branch.id === activeBranchId"
          color="primary"
          @click="handleSelectBranch(branch.id)"
        >
          <template #prepend>
            <VIcon
              :icon="branch.id === activeBranchId ? 'tabler-check' : 'tabler-building-store'"
              size="18"
              class="me-2"
              :color="branch.id === activeBranchId ? 'primary' : undefined"
            />
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ branch.name }}
          </VListItemTitle>

          <VListItemSubtitle v-if="branch.code">
            {{ branch.code }}
          </VListItemSubtitle>

          <template
            v-if="branch.is_main"
            #append
          >
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
            >
              Principal
            </VChip>
          </template>
        </VListItem>
      </VList>
    </VMenu>
  </div>
</template>

<style scoped>
.branch-switcher__title {
  max-inline-size: 140px;
}
</style>
