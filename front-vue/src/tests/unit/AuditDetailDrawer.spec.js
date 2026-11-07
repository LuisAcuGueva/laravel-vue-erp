/* eslint-disable camelcase */
import { beforeAll, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import AuditDetailDrawer from '@/modules/audit/components/AuditDetailDrawer.vue'

const vuetify = createVuetify()

describe('AuditDetailDrawer.vue', () => {
  beforeAll(() => {
    globalThis.ResizeObserver ??= class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
  })

  const mountDrawer = (auditLog, isOpen = true) => {
    return mount(AuditDetailDrawer, {
      props: {
        isDrawerOpen: isOpen,
        auditLog,
      },
      global: {
        plugins: [vuetify],
        stubs: {
          VNavigationDrawer: {
            template: '<div class="v-navigation-drawer-stub"><slot /></div>',
          },
        },
      },
    })
  }

  it('muestra "Creación" en lugar de "CREATED" para eventos de creación y no aplica text-uppercase', () => {
    const wrapper = mountDrawer({
      id: 'log-1',
      event: 'created',
      log_name: 'default',
      subject_type: 'App\\Modules\\Clients\\Models\\Client',
      subject_id: 'client-uuid-1',
      created_at: '2026-10-21T12:00:00Z',
    })

    const chip = wrapper.findComponent({ name: 'VChip' })

    expect(chip.exists()).toBe(true)
    expect(chip.text()).toBe('Creación')
    expect(chip.classes()).not.toContain('text-uppercase')
  })

  it('muestra "Actualización" para eventos updated', () => {
    const wrapper = mountDrawer({
      id: 'log-2',
      event: 'updated',
      log_name: 'default',
      subject_type: 'App\\Modules\\Clients\\Models\\Client',
      subject_id: 'client-uuid-1',
    })

    const chip = wrapper.findComponent({ name: 'VChip' })

    expect(chip.text()).toBe('Actualización')
  })

  it('muestra "Eliminación" para eventos deleted', () => {
    const wrapper = mountDrawer({
      id: 'log-3',
      event: 'deleted',
      log_name: 'default',
      subject_type: 'App\\Modules\\Clients\\Models\\Client',
      subject_id: 'client-uuid-1',
    })

    const chip = wrapper.findComponent({ name: 'VChip' })

    expect(chip.text()).toBe('Eliminación')
  })

  it('muestra "Restauración" para eventos restored', () => {
    const wrapper = mountDrawer({
      id: 'log-4',
      event: 'restored',
      log_name: 'default',
      subject_type: 'App\\Modules\\Clients\\Models\\Client',
      subject_id: 'client-uuid-1',
    })

    const chip = wrapper.findComponent({ name: 'VChip' })

    expect(chip.text()).toBe('Restauración')
  })

  it('muestra "Sincronización de sedes" para el evento o descripción branches_synced con color diferenciado (primary)', () => {
    const wrapper = mountDrawer({
      id: 'log-5',
      event: null,
      description: 'branches_synced',
      log_name: 'default',
      subject_type: 'App\\Modules\\Users\\Models\\User',
      subject_id: 'user-uuid-1',
    })

    const chip = wrapper.findComponent({ name: 'VChip' })

    expect(chip.text()).toBe('Sincronización de sedes')
    expect(chip.props('color')).toBe('primary')
  })
})
