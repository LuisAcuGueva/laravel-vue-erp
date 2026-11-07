<script setup>
import { computed, defineAsyncComponent, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNavigationMenu } from '@/composables/useNavigationMenu'

defineOptions({
  inheritAttrs: false,
})

const router = useRouter()
const { navItems } = useNavigationMenu()

const isAppSearchBarVisible = ref(false)
const searchQuery = ref('')

const flattenNavItems = items => items.flatMap(item => {
  if (Array.isArray(item.children))
    return flattenNavItems(item.children)

  if (!item.to)
    return []

  return [{
    title: item.title,
    icon: item.icon?.icon || 'tabler-circle',
    url: item.to,
  }]
})

const searchableItems = computed(() => {
  const seen = new Set()

  return flattenNavItems(navItems.value || []).filter(item => {
    const path = typeof item.url === 'string' ? item.url : item.url?.path || item.url?.name

    if (!path || seen.has(path))
      return false

    seen.add(path)

    return true
  })
})

// Sugerencias agrupadas por sección del menú (headings), sin recortar ítems.
const suggestionGroups = computed(() => {
  const groups = []
  let current = { title: 'General', content: [] }

  for (const item of navItems.value || []) {
    if (item.heading) {
      if (current.content.length)
        groups.push(current)
      current = { title: item.heading, content: [] }
      continue
    }

    current.content.push(...flattenNavItems([item]))
  }

  if (current.content.length)
    groups.push(current)

  return groups
})

const searchResult = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  if (!query)
    return []

  const matches = searchableItems.value.filter(item => item.title.toLowerCase().includes(query))

  return matches.length ? [{ title: 'Resultados', children: matches }] : []
})

const noDataSuggestions = computed(() => searchableItems.value.slice(0, 3))

const closeSearchBar = () => {
  isAppSearchBarVisible.value = false
  searchQuery.value = ''
}

const redirectToSuggestedPage = selected => {
  router.push(selected.url)
  closeSearchBar()
}

const isMac = computed(() => {
  if (typeof navigator === 'undefined')
    return false

  return /Mac|iPhone|iPod|iPad/i.test(navigator.userAgent || navigator.platform || '')
})

const LazyAppBarSearch = defineAsyncComponent(() => import('@core/components/AppBarSearch.vue'))
</script>

<template>
  <!-- 👉 Desktop & Tablet Search Trigger (Sleek unified pill/input) -->
  <div
    class="nav-search-trigger d-none d-md-flex align-center cursor-pointer me-2"
    v-bind="$attrs"
    role="button"
    tabindex="0"
    aria-label="Buscar en la aplicación"
    @click="isAppSearchBarVisible = !isAppSearchBarVisible"
    @keydown.enter="isAppSearchBarVisible = true"
  >
    <VIcon
      size="18"
      icon="tabler-search"
      class="search-trigger-icon me-2"
    />

    <span class="search-trigger-text text-body-2 text-medium-emphasis flex-grow-1">
      Buscar...
    </span>

    <kbd class="search-command-kbd ms-2">
      <span class="command-symbol">{{ isMac ? '⌘' : 'Ctrl' }}</span>
      <span class="command-char">K</span>
    </kbd>
  </div>

  <!-- 👉 Mobile Search Trigger (Compact Icon button) -->
  <IconBtn
    class="d-flex d-md-none"
    aria-label="Buscar"
    @click="isAppSearchBarVisible = !isAppSearchBarVisible"
  >
    <VIcon
      icon="tabler-search"
      size="22"
    />
  </IconBtn>

  <!-- 👉 App Bar Search -->
  <LazyAppBarSearch
    v-model:is-dialog-visible="isAppSearchBarVisible"
    :search-results="searchResult"
    :is-loading="false"
    @search="searchQuery = $event"
  >
    <!-- suggestion -->
    <template #suggestions>
      <VCardText class="app-bar-search-suggestions pa-6">
        <VRow v-if="suggestionGroups.length">
          <VCol
            v-for="suggestion in suggestionGroups"
            :key="suggestion.title"
            cols="12"
            sm="6"
          >
            <p
              class="custom-letter-spacing text-disabled text-uppercase py-2 px-4 mb-0"
              style="font-size: 0.75rem; line-height: 0.875rem;"
            >
              {{ suggestion.title }}
            </p>
            <VList class="card-list">
              <VListItem
                v-for="item in suggestion.content"
                :key="item.title"
                class="app-bar-search-suggestion mx-4 mt-2"
                @click="redirectToSuggestedPage(item)"
              >
                <VListItemTitle>{{ item.title }}</VListItemTitle>
                <template #prepend>
                  <VIcon
                    class="me-n1"
                    :icon="item.icon"
                    size="20"
                  />
                </template>
              </VListItem>
            </VList>
          </VCol>
        </VRow>
      </VCardText>
    </template>

    <!-- no data suggestion -->
    <template #noDataSuggestion>
      <div class="mt-9">
        <span class="d-flex justify-center text-disabled mb-2">Intenta buscar</span>
        <h6
          v-for="suggestion in noDataSuggestions"
          :key="suggestion.title"
          class="app-bar-search-suggestion text-h6 font-weight-regular cursor-pointer py-2 px-4"
          @click="redirectToSuggestedPage(suggestion)"
        >
          <VIcon
            class="me-2"
            :icon="suggestion.icon"
            size="20"
          />
          <span>{{ suggestion.title }}</span>
        </h6>
      </div>
    </template>

    <!-- search result -->
    <template #searchResult="{ item }">
      <VListSubheader class="text-disabled custom-letter-spacing font-weight-regular ps-4">
        {{ item.title }}
      </VListSubheader>
      <VListItem
        v-for="list in item.children"
        :key="list.title"
        :to="list.url"
        @click="closeSearchBar"
      >
        <template #prepend>
          <VIcon
            class="me-n1"
            :icon="list.icon"
            size="20"
          />
        </template>
        <template #append>
          <VIcon
            class="enter-icon flip-in-rtl"
            icon="tabler-corner-down-left"
            size="20"
          />
        </template>
        <VListItemTitle>
          {{ list.title }}
        </VListItemTitle>
      </VListItem>
    </template>
  </LazyAppBarSearch>
</template>

<style lang="scss">
@use "@styles/variables/vuetify.scss";

.nav-search-trigger {
  block-size: 38px;
  border: 1px solid rgba(var(--v-border-color), 0.16);
  border-radius: 8px;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  inline-size: 210px;
  padding-block: 0;
  padding-inline: 12px 10px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;

  .search-trigger-icon {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    transition: color 0.2s ease, transform 0.2s ease;
  }

  .search-trigger-text {
    letter-spacing: 0.15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .search-command-kbd {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    block-size: 22px;
    min-inline-size: 26px;
    padding-block: 0;
    padding-inline: 6px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 5px;
    background-color: rgba(var(--v-theme-on-surface), 0.06);
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    transition: all 0.2s ease;

    .command-symbol {
      font-size: 0.75rem;
      line-height: 1;
    }

    .command-char {
      font-size: 0.75rem;
      line-height: 1;
    }
  }

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.45);
    background-color: rgba(var(--v-theme-on-surface), 0.065);
    box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.1);

    .search-trigger-icon {
      color: rgb(var(--v-theme-primary));
      transform: scale(1.05);
    }

    .search-command-kbd {
      border-color: rgba(var(--v-theme-primary), 0.35);
      background-color: rgba(var(--v-theme-primary), 0.08);
      color: rgb(var(--v-theme-primary));
    }
  }

  &:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.25);
  }
}

.app-bar-search-dialog {
  .custom-letter-spacing {
    letter-spacing: 0.8px;
  }

  .card-list {
    --v-card-list-gap: 8px;
  }
}
</style>
