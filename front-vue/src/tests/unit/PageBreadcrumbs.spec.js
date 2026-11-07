import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createVuetify } from 'vuetify'
import PageBreadcrumbs from '@/layouts/components/PageBreadcrumbs.vue'

const Empty = { template: '<div />' }

const routes = [
  { path: '/', name: 'root', component: Empty, meta: { title: 'Inicio' } },
  { path: '/users', name: 'users', component: Empty, meta: { title: 'Usuarios' } },
  { path: '/audit', name: 'audit', component: Empty, meta: { title: 'Auditoría' } },
  { path: '/clients', name: 'clients', component: Empty, meta: { title: 'Clientes' } },
  { path: '/clients/:id', name: 'clients-id', component: Empty, meta: { title: 'Ficha del Cliente', navActiveLink: 'clients' } },
  { path: '/profile', name: 'profile', component: Empty, meta: { title: 'Mi cuenta' } },
]

const mountAt = async path => {
  const router = createRouter({ history: createMemoryHistory(), routes })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(PageBreadcrumbs, { global: { plugins: [router, createVuetify()] } })

  return {
    title: wrapper.find('.page-breadcrumbs__title').text(),
    trail: wrapper.findAll('.page-breadcrumbs__trail a, .page-breadcrumbs__trail span').map(el => el.text()),
    html: wrapper.html(),
  }
}

describe('PageBreadcrumbs', () => {
  it('ruta del menú: Inicio › Seguridad › Usuarios', async () => {
    const { title, trail, html } = await mountAt('/users')

    expect(title).toBe('Usuarios')
    expect(trail).toEqual(['Inicio', 'Seguridad', 'Usuarios'])
    expect(html).not.toMatch(/Users|Security/)
  })

  it('link directo del menú: Inicio › Auditoría', async () => {
    const { trail } = await mountAt('/audit')

    expect(trail).toEqual(['Inicio', 'Auditoría'])
  })

  it('subpágina: Inicio › Clientes › Ficha del Cliente', async () => {
    const { title, trail } = await mountAt('/clients/abc')

    expect(title).toBe('Ficha del Cliente')
    expect(trail).toEqual(['Inicio', 'Clientes', 'Ficha del Cliente'])
  })

  it('ruta fuera del menú: Inicio › Mi cuenta', async () => {
    const { title, trail, html } = await mountAt('/profile')

    expect(title).toBe('Mi cuenta')
    expect(trail).toEqual(['Inicio', 'Mi cuenta'])
    expect(html).not.toMatch(/Profile/)
  })

  it('en Inicio no muestra la ruta', async () => {
    const { title, trail } = await mountAt('/')

    expect(title).toBe('Inicio')
    expect(trail).toEqual([])
  })

  it('las cabeceras de sección no aparecen en la ruta', async () => {
    const { html } = await mountAt('/users')

    expect(html).not.toContain('MANTENIMIENTO')
  })
})
