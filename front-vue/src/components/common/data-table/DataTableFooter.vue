<script setup>
const props = defineProps({
  page: {
    type: Number,
    default: 1,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  itemsPerPageOptions: {
    type: Array,
    default: () => [10, 25, 50, 100],
  },
})

const emit = defineEmits(['update:page', 'update:itemsPerPage'])

const format = value => Number(value || 0).toLocaleString('es-PE')

const pageCount = computed(() => Math.max(1, Math.ceil(props.totalItems / props.itemsPerPage)))

const rangeText = computed(() => {
  if (!props.totalItems)
    return 'Sin registros'

  const from = (props.page - 1) * props.itemsPerPage + 1
  const to = Math.min(props.page * props.itemsPerPage, props.totalItems)

  return `Mostrando ${format(from)}–${format(to)} de ${format(props.totalItems)}`
})

const perPageItems = computed(() => props.itemsPerPageOptions.map(option => (
  typeof option === 'object' ? option : { title: String(option), value: option }
)))
</script>

<template>
  <div class="data-table-footer d-flex flex-wrap align-center justify-space-between gap-4 px-6 py-3">
    <span
      class="data-table-footer__range text-body-2 text-medium-emphasis"
      aria-live="polite"
    >
      {{ rangeText }}
    </span>

    <div class="d-flex flex-wrap align-center gap-4">
      <div class="d-flex align-center gap-2">
        <span class="text-body-2 text-medium-emphasis text-no-wrap">Filas por página</span>
        <VSelect
          :model-value="props.itemsPerPage"
          :items="perPageItems"
          density="compact"
          hide-details
          aria-label="Filas por página"
          style="inline-size: 5.5rem;"
          @update:model-value="emit('update:itemsPerPage', $event)"
        />
      </div>

      <VPagination
        :model-value="props.page"
        :length="pageCount"
        :total-visible="5"
        show-first-last-page
        first-icon="tabler-chevrons-left"
        last-icon="tabler-chevrons-right"
        prev-icon="tabler-chevron-left"
        next-icon="tabler-chevron-right"
        size="small"
        @update:model-value="emit('update:page', $event)"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
@media (max-width: 599.98px) {
  .data-table-footer {
    flex-direction: column;
    align-items: stretch !important;
  }
}
</style>
