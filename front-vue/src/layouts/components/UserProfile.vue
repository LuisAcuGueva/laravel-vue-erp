<script setup>
import { useAuth } from '@/composables/useAuth'

const { user, roles, logout } = useAuth()

const displayName = computed(() => user.value?.name || 'Administrador')
const displayEmail = computed(() => user.value?.email || 'admin@sistema.local')

const roleLabel = computed(() => {
  const role = roles.value?.[0]
  if (role)
    return role.replace(/[_-]/g, ' ').replace(/\b\w/g, char => char.toUpperCase())

  return 'Super Admin'
})

const initials = computed(() => avatarText(displayName.value))

const menuItems = computed(() => [
  { title: 'Inicio', icon: 'tabler-smart-home', to: '/' },
  { title: 'Mi Perfil', icon: 'tabler-user', to: '/profile' },
])

const handleLogout = async () => {
  await logout()
}
</script>

<template>
  <VBadge
    dot
    location="bottom right"
    offset-x="3"
    offset-y="3"
    bordered
    color="success"
  >
    <VAvatar
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <span class="text-sm font-weight-bold">{{ initials }}</span>

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="250"
        location="bottom end"
        offset="14px"
      >
        <VList>
          <!-- 👉 User Avatar & Info -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    color="primary"
                    variant="tonal"
                  >
                    <span class="text-sm font-weight-bold">{{ initials }}</span>
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ displayName }}
            </VListItemTitle>
            <VListItemSubtitle class="text-truncate">
              {{ displayEmail }}
            </VListItemSubtitle>

            <template #append>
              <VChip
                color="primary"
                size="x-small"
                variant="tonal"
              >
                {{ roleLabel }}
              </VChip>
            </template>
          </VListItem>

          <VDivider class="my-2" />

          <!-- 👉 Links -->
          <VListItem
            v-for="item in menuItems"
            :key="item.to"
            :to="item.to"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                :icon="item.icon"
                size="22"
              />
            </template>

            <VListItemTitle>{{ item.title }}</VListItemTitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- 👉 Logout -->
          <VListItem @click="handleLogout">
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-logout"
                size="22"
              />
            </template>

            <VListItemTitle>Cerrar sesión</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
