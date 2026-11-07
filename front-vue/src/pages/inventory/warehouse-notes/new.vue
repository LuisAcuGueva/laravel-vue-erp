<script setup>
import WarehouseNoteForm from '@/modules/inventory/components/notes/WarehouseNoteForm.vue'

definePage({
  meta: {
    action: 'warehouse_notes.create',
    title: 'Nueva nota de almacén',
    icon: 'tabler-file-invoice',
    description: 'Registro de movimientos de almacén: entradas, salidas o traslados.',
  },
})
</script>

<template>
  <WarehouseNoteForm is-new />
</template>
