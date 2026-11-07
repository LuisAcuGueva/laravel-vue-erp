<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const progress = item => {
  const quantity = Number(item.quantity) || 0

  return quantity > 0 ? Math.min(100, (Number(item.ordered_quantity) / quantity) * 100) : 0
}

const itemLabel = item => (item.catalog_item
  ? `${item.catalog_item.code} · ${item.catalog_item.name}${item.presentation ? ` (${item.presentation.name})` : ''}`
  : item.description)
</script>

<template>
  <VTable density="comfortable">
    <thead>
      <tr>
        <th>#</th>
        <th>ARTÍCULO / DESCRIPCIÓN</th>
        <th class="text-end">
          SOLICITADO
        </th>
        <th class="text-end">
          ORDENADO
        </th>
        <th style="min-width: 160px;">
          AVANCE
        </th>
        <th>NOTAS</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="item in props.items"
        :key="item.id"
      >
        <td>{{ item.line }}</td>
        <td>{{ itemLabel(item) }}</td>
        <td class="text-end">
          {{ Number(item.quantity) }}
        </td>
        <td class="text-end">
          {{ Number(item.ordered_quantity) }}
        </td>
        <td>
          <VProgressLinear
            :model-value="progress(item)"
            :color="progress(item) >= 100 ? 'success' : 'primary'"
            height="8"
            rounded
          />
        </td>
        <td>{{ item.notes || '—' }}</td>
      </tr>
    </tbody>
  </VTable>
</template>
