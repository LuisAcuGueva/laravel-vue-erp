/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import ClientPicker from '@/components/common/ClientPicker.vue'
import { clientsApi } from '@/modules/clients/services/clientsApi'

vi.mock('@/modules/clients/services/clientsApi', () => ({
  clientsApi: {
    options: vi.fn(),
    create: vi.fn(),
  },
}))

const mockClients = [
  {
    id: 'cli-1',
    client_type: 'company',
    document_type: 'RUC',
    document_number: '20601234567',
    name: 'COMERCIAL ANDINA S.A.C.',
    address: 'Av. Los Próceres 123',
    default_price_type: 'wholesale',
    credit_limit: 10000,
    is_system: false,
  },
  {
    id: 'cli-2',
    client_type: 'person',
    document_type: 'DNI',
    document_number: '45678912',
    name: 'Juan Pérez López',
    address: 'Calle Los Pinos 456',
    default_price_type: 'retail',
    credit_limit: 0,
    is_system: false,
  },
]

const vuetify = createVuetify()

const mountPicker = async (props = {}) => {
  const wrapper = mount(ClientPicker, {
    props,
    global: { plugins: [vuetify] },
  })

  await flushPromises()

  return { wrapper, autocomplete: wrapper.findComponent({ name: 'VAutocomplete' }) }
}

describe('ClientPicker', () => {
  beforeAll(() => {
    globalThis.ResizeObserver ??= class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    clientsApi.options.mockResolvedValue({ data: mockClients })
  })

  it('renderiza VAutocomplete con label y placeholder', async () => {
    const { wrapper, autocomplete } = await mountPicker({
      label: 'Cliente',
      placeholder: 'Buscar cliente...',
    })

    expect(wrapper.findAllComponents({ name: 'VAutocomplete' })).toHaveLength(1)
    expect(autocomplete.props('label')).toBe('Cliente')
    expect(autocomplete.props('placeholder')).toBe('Buscar cliente...')
  })

  it('permite valor inicial con initialItem', async () => {
    const initial = mockClients[0]

    const { autocomplete } = await mountPicker({
      modelValue: initial.id,
      initialItem: initial,
    })

    expect(autocomplete.props('modelValue')).toBe(initial.id)
  })

  it('filtra clientes RUC cuando requireRuc es true', async () => {
    const { autocomplete } = await mountPicker({
      requireRuc: true,
      initialItem: mockClients[0],
    })

    await autocomplete.vm.$emit('focus')
    await flushPromises()

    const items = autocomplete.props('items')

    expect(items.every(item => item.document_type === 'RUC')).toBe(true)
  })

  it('item-title no renderiza "undefined" cuando el model-value no coincide con ningún item cargado', async () => {
    const { autocomplete } = await mountPicker({
      modelValue: 'cli-999',
    })

    const itemTitle = autocomplete.props('itemTitle')

    expect(itemTitle('cli-999')).toBe('')
  })

  it('item-title no renderiza "undefined" cuando el cliente no tiene name', async () => {
    const { autocomplete } = await mountPicker({
      modelValue: 'cli-1',
      initialItem: { id: 'cli-1', document_type: 'RUC', document_number: '20601234567' },
    })

    const itemTitle = autocomplete.props('itemTitle')

    expect(itemTitle({ id: 'cli-1', document_type: 'RUC', document_number: '20601234567' })).not.toContain('undefined')
  })

  it('emite null al limpiar la selección', async () => {
    const { wrapper } = await mountPicker({
      modelValue: 'cli-1',
      initialItem: mockClients[0],
    })

    const autocomplete = wrapper.findComponent({ name: 'VAutocomplete' })

    await autocomplete.vm.$emit('update:model-value', null)
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue').slice(-1)[0]).toEqual([null])
  })
})
