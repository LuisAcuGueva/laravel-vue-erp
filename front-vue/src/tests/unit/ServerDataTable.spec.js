import { beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import DataTableColumnMenu from '@/components/common/data-table/DataTableColumnMenu.vue'
import DataTableFooter from '@/components/common/data-table/DataTableFooter.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAuthStore } from '@/modules/auth/stores/authStore'

const vuetify = createVuetify()

const headers = [
  { title: 'CÓDIGO', key: 'code', alwaysVisible: true },
  { title: 'NOMBRE', key: 'name' },
  { title: 'TELÉFONO', key: 'phone', hidden: true },
  { title: 'ACCIONES', key: 'actions' },
]

const mountTable = (props = {}, slots = {}) => mount(ServerDataTable, {
  props: { tableId: 'branches', headers, items: [], totalItems: 0, ...props },
  slots,
  global: { plugins: [vuetify] },
})

const headerKeys = wrapper => wrapper.findComponent({ name: 'VDataTableServer' }).props('headers').map(h => h.key)

describe('ServerDataTable', () => {
  beforeAll(() => {
    globalThis.ResizeObserver ??= class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    window.localStorage.clear()
    useAuthStore().user = { id: 'user-1' }
  })

  describe('contador y footer', () => {
    it('no muestra contador en la barra; el total solo aparece en el footer', () => {
      const wrapper = mountTable({ totalItems: 1352 })

      expect(wrapper.find('[data-testid="dt-counter"]').exists()).toBe(false)
      expect(wrapper.find('.data-table-toolbar').text()).not.toContain('registros encontrados')
    })

    it('muestra «Mostrando 11–20 de 352»', () => {
      const wrapper = mount(DataTableFooter, {
        props: { page: 2, itemsPerPage: 10, totalItems: 352 },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.text()).toContain('Mostrando 11–20 de 352')
    })

    it('muestra «Sin registros» con total 0', () => {
      const wrapper = mount(DataTableFooter, {
        props: { page: 1, itemsPerPage: 10, totalItems: 0 },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.text()).toContain('Sin registros')
    })

    it('cambiar filas por página emite options con página 1', async () => {
      const wrapper = mountTable({ totalItems: 100, options: { page: 3, itemsPerPage: 10, sortBy: [] } })

      wrapper.findComponent(DataTableFooter).vm.$emit('update:itemsPerPage', 25)
      await flushPromises()

      expect(wrapper.emitted('update:options').at(-1)).toEqual([{ page: 1, itemsPerPage: 25, sortBy: [] }])
    })

    it('cambiar de página emite options', async () => {
      const wrapper = mountTable({ totalItems: 100 })

      wrapper.findComponent(DataTableFooter).vm.$emit('update:page', 2)
      await flushPromises()

      expect(wrapper.emitted('update:options').at(-1)).toEqual([{ page: 2, itemsPerPage: 10, sortBy: [] }])
    })

    it('no usa el footer por defecto de Vuetify', () => {
      const wrapper = mountTable()

      expect(wrapper.findComponent({ name: 'VDataTableServer' }).props('hideDefaultFooter')).toBe(true)
    })
  })

  describe('estado vacío', () => {
    it('muestra el CTA de crear si es creatable y no hay filtros', async () => {
      const wrapper = mountTable({ creatable: true, createLabel: 'Nueva sede' })

      await flushPromises()

      expect(wrapper.find('[data-testid="dt-empty"]').text()).toContain('No se encontraron registros')

      const cta = wrapper.find('[data-testid="dt-empty-create"]')

      expect(cta.text()).toBe('Nueva sede')
      await cta.trigger('click')
      expect(wrapper.emitted('create')).toHaveLength(1)
    })

    it('sin creatable no muestra CTA', async () => {
      const wrapper = mountTable()

      await flushPromises()

      expect(wrapper.find('[data-testid="dt-empty-create"]').exists()).toBe(false)
    })

    it('con búsqueda cambia el mensaje y oculta el CTA', async () => {
      const wrapper = mountTable({ creatable: true, search: 'lima' })

      await flushPromises()

      expect(wrapper.find('[data-testid="dt-empty"]').text()).toContain('No hay resultados para "lima"')
      expect(wrapper.find('[data-testid="dt-empty-create"]').exists()).toBe(false)
    })

    it('con filtros cambia el mensaje', async () => {
      const wrapper = mountTable({ creatable: true, hasFilters: true })

      await flushPromises()

      expect(wrapper.find('[data-testid="dt-empty"]').text()).toContain('No hay resultados para los filtros aplicados')
    })
  })

  describe('barra de herramientas', () => {
    it('muestra solo refrescar y columnas por defecto', () => {
      const wrapper = mountTable()

      expect(wrapper.find('[data-testid="dt-refresh"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="dt-columns"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="dt-export"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="dt-import"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="dt-create"]').exists()).toBe(false)
    })

    it('muestra botones según props y emite sus eventos', async () => {
      const wrapper = mountTable({ exportable: true, importable: true, creatable: true, createLabel: 'Nueva sede' })

      expect(wrapper.find('[data-testid="dt-create"]').attributes('aria-label')).toBe('Nueva sede')

      await wrapper.find('[data-testid="dt-refresh"]').trigger('click')
      await wrapper.find('[data-testid="dt-export"]').trigger('click')
      await wrapper.find('[data-testid="dt-create"]').trigger('click')

      expect(wrapper.emitted('refresh')).toHaveLength(1)
      expect(wrapper.emitted('export')).toHaveLength(1)
      expect(wrapper.emitted('create')).toHaveLength(1)
    })

    it('oculta refrescar y columnas si se desactivan', () => {
      const wrapper = mountTable({ refreshable: false, columnToggle: false })

      expect(wrapper.find('[data-testid="dt-refresh"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="dt-columns"]').exists()).toBe(false)
    })

    it('importar emite el archivo elegido', async () => {
      const wrapper = mountTable({ importable: true })
      const input = wrapper.find('[data-testid="dt-import-input"]')
      const file = new File(['a,b'], 'datos.csv', { type: 'text/csv' })

      expect(input.attributes('accept')).toBe('.csv,.xlsx')

      Object.defineProperty(input.element, 'files', { value: [file] })
      await input.trigger('change')

      expect(wrapper.emitted('import')[0]).toEqual([file])
    })

    it('la búsqueda emite update:search', async () => {
      const wrapper = mountTable({ searchPlaceholder: 'Código o nombre' })
      const field = wrapper.findComponent({ name: 'VTextField' })

      expect(field.props('label')).toBe('Buscar')
      field.vm.$emit('update:modelValue', 'sede')
      await flushPromises()

      expect(wrapper.emitted('update:search').at(-1)).toEqual(['sede'])
    })
  })

  describe('columnas', () => {
    it('oculta por defecto las columnas hidden', async () => {
      const wrapper = mountTable()

      await flushPromises()

      expect(headerKeys(wrapper)).toEqual(['code', 'name', 'actions'])
    })

    it('persiste la elección por usuario y tabla y sobrevive a remontar', async () => {
      const wrapper = mountTable()

      await flushPromises()
      wrapper.findComponent(DataTableColumnMenu).vm.$emit('update:modelValue', ['name'])

      // Simula el toggle real del menú (que además escribe en localStorage).
      window.localStorage.setItem('dt-columns:user-1:branches', JSON.stringify(['name']))
      await flushPromises()

      expect(headerKeys(wrapper)).toEqual(['code', 'phone', 'actions'])

      const remounted = mountTable()

      await flushPromises()

      expect(headerKeys(remounted)).toEqual(['code', 'phone', 'actions'])
    })

    it('el menú guarda en dt-columns:<userId>:<tableId> y Restablecer vuelve al default', async () => {
      const wrapper = mount(DataTableColumnMenu, {
        props: { tableId: 'branches', headers, modelValue: [] },
        global: { plugins: [vuetify] },
      })

      await flushPromises()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([['phone']])

      await wrapper.setProps({ modelValue: ['phone'] })
      wrapper.vm.$.setupState.toggle('name', false)

      expect(JSON.parse(window.localStorage.getItem('dt-columns:user-1:branches'))).toEqual(['phone', 'name'])

      wrapper.vm.$.setupState.reset()

      expect(window.localStorage.getItem('dt-columns:user-1:branches')).toBeNull()
      expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([['phone']])
    })

    it('no permite ocultar actions ni alwaysVisible', () => {
      const wrapper = mount(DataTableColumnMenu, {
        props: { tableId: 'branches', headers, modelValue: [] },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.vm.$.setupState.toggleable.map(h => h.key)).toEqual(['name', 'phone'])
    })
  })
})

describe('RowActionButton', () => {
  it.each([
    ['view', 'info', 'tabler-eye', 'Ver detalle'],
    ['edit', 'success', 'tabler-pencil', 'Editar'],
    ['delete', 'error', 'tabler-trash', 'Eliminar'],
  ])('%s usa color %s, ícono %s y aria-label', (action, color, icon, label) => {
    const wrapper = mount(RowActionButton, { props: { action }, global: { plugins: [vuetify] } })
    const btn = wrapper.findComponent({ name: 'VBtn' })

    expect(btn.props('color')).toBe(color)
    expect(btn.props('variant')).toBe('outlined')
    expect(btn.attributes('aria-label')).toBe(label)
    expect(wrapper.findComponent({ name: 'VIcon' }).props('icon')).toBe(icon)
  })

  it('custom acepta ícono, color y tooltip propios', () => {
    const wrapper = mount(RowActionButton, {
      props: { action: 'custom', icon: 'tabler-key', tooltip: 'Permisos' },
      global: { plugins: [vuetify] },
    })

    expect(wrapper.findComponent({ name: 'VBtn' }).props('color')).toBe('primary')
    expect(wrapper.findComponent({ name: 'VBtn' }).attributes('aria-label')).toBe('Permisos')
  })

  it('en loading queda deshabilitado', () => {
    const wrapper = mount(RowActionButton, { props: { action: 'delete', loading: true }, global: { plugins: [vuetify] } })

    expect(wrapper.findComponent({ name: 'VBtn' }).props('disabled')).toBe(true)
  })
})
