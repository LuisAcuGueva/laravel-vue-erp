<script setup>
import navItems from '@/navigation/vertical'
import { findNavTrail } from '@/utils/navigation'

const route = useRoute()

const HOME = { title: 'Inicio', to: '/' }

// El título sale siempre de `meta.title`; nunca del path de la URL.
const pageLabel = computed(() => route.meta?.title || '')

// Ruta: Inicio › <grupos…> › <página>, desde el árbol de navegación. Fuera del menú: Inicio › meta.title.
const breadcrumbs = computed(() => {
  if (route.name === 'root')
    return [HOME]

  const trail = findNavTrail(navItems, route.name)

  if (trail.length)
    return [HOME, ...trail]

  // Subpágina (`/new`, `/[id]`): Inicio › <grupos…> › <módulo> › meta.title.
  const moduleTrail = findNavTrail(navItems, route.meta?.navActiveLink)

  if (moduleTrail.length && pageLabel.value)
    return [HOME, ...moduleTrail, { title: pageLabel.value }]

  return pageLabel.value ? [HOME, { title: pageLabel.value }] : [HOME]
})
</script>

<template>
  <div class="page-breadcrumbs">
    <p class="page-breadcrumbs__title">
      {{ pageLabel }}
    </p>

    <nav
      v-if="breadcrumbs.length > 1"
      class="page-breadcrumbs__trail"
      aria-label="Ruta de navegación"
    >
      <template
        v-for="(crumb, index) in breadcrumbs"
        :key="`${index}-${crumb.title}`"
      >
        <VIcon
          v-if="index > 0"
          class="page-breadcrumbs__separator"
          icon="tabler-chevron-right"
          size="14"
        />

        <RouterLink
          v-if="index < breadcrumbs.length - 1 && crumb.to"
          class="page-breadcrumbs__link"
          :to="crumb.to"
        >
          {{ crumb.title }}
        </RouterLink>

        <span
          v-else-if="index < breadcrumbs.length - 1"
          class="page-breadcrumbs__group"
        >
          {{ crumb.title }}
        </span>

        <span
          v-else
          class="page-breadcrumbs__current"
          aria-current="page"
        >
          {{ crumb.title }}
        </span>
      </template>
    </nav>
  </div>
</template>

<style lang="scss" scoped>
.page-breadcrumbs {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;

  &__title {
    overflow: hidden;
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.25rem;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__trail {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    min-inline-size: 0;
    font-size: 0.8125rem;
    line-height: 1.125rem;
  }

  &__separator {
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  }

  &__link {
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;

    &:hover {
      color: rgb(var(--v-theme-primary));
    }
  }

  &__group,
  &__current {
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
