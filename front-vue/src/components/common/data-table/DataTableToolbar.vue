<script setup>
const props = defineProps({
  search: {
    type: String,
    default: '',
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  searchPlaceholder: {
    type: String,
    default: '',
  },
  createLabel: {
    type: String,
    default: 'Nuevo registro',
  },
  creatable: {
    type: Boolean,
    default: false,
  },
  exportable: {
    type: Boolean,
    default: false,
  },
  exporting: {
    type: Boolean,
    default: false,
  },
  importable: {
    type: Boolean,
    default: false,
  },
  refreshable: {
    type: Boolean,
    default: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:search', 'refresh', 'create', 'export', 'import'])

const fileInput = ref(null)

const openFilePicker = () => fileInput.value?.click()

const onFileSelected = event => {
  const [file] = event.target.files || []

  if (file)
    emit('import', file)

  // Permite volver a elegir el mismo archivo.
  event.target.value = ''
}
</script>

<template>
  <div class="data-table-toolbar d-flex flex-wrap align-center gap-4 px-6 py-4">
    <div class="data-table-toolbar__filters d-flex flex-wrap align-center gap-4 flex-grow-1">
      <VTextField
        v-if="props.searchable"
        :model-value="props.search"
        label="Buscar"
        :placeholder="props.searchPlaceholder"
        density="compact"
        prepend-inner-icon="tabler-search"
        clearable
        class="data-table-toolbar__search"
        @update:model-value="emit('update:search', $event ?? '')"
      />

      <slot name="filters" />
    </div>

    <div class="data-table-toolbar__actions d-flex align-center gap-2">
      <VTooltip
        v-if="props.refreshable"
        text="Refrescar"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            v-bind="tooltipProps"
            icon
            variant="tonal"
            rounded="circle"
            size="38"
            color="secondary"
            aria-label="Refrescar"
            data-testid="dt-refresh"
            :disabled="props.loading"
            @click="emit('refresh')"
          >
            <VIcon
              icon="tabler-refresh"
              size="20"
            />
          </VBtn>
        </template>
      </VTooltip>

      <VTooltip
        v-if="props.exportable"
        text="Exportar"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            v-bind="tooltipProps"
            icon
            variant="tonal"
            rounded="circle"
            size="38"
            color="primary"
            aria-label="Exportar"
            data-testid="dt-export"
            :loading="props.exporting"
            :disabled="props.exporting"
            @click="emit('export')"
          >
            <VIcon
              icon="tabler-file-export"
              size="20"
            />
          </VBtn>
        </template>
      </VTooltip>

      <template v-if="props.importable">
        <VTooltip text="Importar">
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              variant="tonal"
              rounded="circle"
              size="38"
              color="success"
              aria-label="Importar"
              data-testid="dt-import"
              @click="openFilePicker"
            >
              <VIcon
                icon="tabler-file-import"
                size="20"
              />
            </VBtn>
          </template>
        </VTooltip>
        <input
          ref="fileInput"
          type="file"
          accept=".csv,.xlsx"
          class="d-none"
          data-testid="dt-import-input"
          @change="onFileSelected"
        >
      </template>

      <VTooltip
        v-if="props.creatable"
        :text="props.createLabel"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            v-bind="tooltipProps"
            icon
            variant="elevated"
            rounded="circle"
            size="38"
            color="primary"
            :aria-label="props.createLabel"
            data-testid="dt-create"
            @click="emit('create')"
          >
            <VIcon
              icon="tabler-plus"
              size="20"
            />
          </VBtn>
        </template>
      </VTooltip>

      <slot name="append" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.data-table-toolbar {
  &__search {
    flex: 1 1 14rem;
    max-inline-size: 20rem;
  }

  &__filters > :deep(.v-input) {
    flex: 1 1 10rem;
    max-inline-size: 14rem;
  }

  &__actions {
    align-self: flex-start;
    padding-block-start: 1px;
  }

  @media (max-width: 599.98px) {
    flex-direction: column;
    align-items: stretch !important;

    &__filters {
      flex-direction: column;
      align-items: stretch !important;

      > :deep(.v-input),
      .data-table-toolbar__search {
        max-inline-size: none;
      }
    }

    &__actions {
      justify-content: flex-end;
      align-self: stretch;
      padding-block-start: 0;
    }
  }
}
</style>
