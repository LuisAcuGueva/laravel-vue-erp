import { describe, expect, it } from 'vitest'
import navItems from '@/navigation/vertical'

/**
 * Metadatos de ruta (spec 031, design §8.3): toda página con `meta.action` declara
 * `title`, `icon` y `description`, y su ícono coincide con el del menú.
 */
const pages = import.meta.glob('/src/pages/**/*.vue', { query: '?raw', import: 'default', eager: true })

// Mismo criterio de nombres que `getRouteName` en vite.config.js (rutas por archivo).
const routeNameOf = path => {
  const route = path
    .replace(/^\/src\/pages\//, '')
    .replace(/\.vue$/, '')
    .replace(/(^|\/)index$/, '')
    .split('/')
    .filter(Boolean)
    .join('-')

  return route || 'root'
}

const extractMeta = source => {
  const block = source.match(/definePage\(\{[\s\S]*?\n\}\)/)?.[0]
  if (!block)
    return null

  const read = key => block.match(new RegExp(`\\b${key}:\\s*'([^']*)'`))?.[1]

  // `action` puede ser un permiso o una lista de permisos (basta uno).
  const action = read('action') ?? block.match(/\baction:\s*\[([^\]]*)\]/)?.[1]?.trim()

  return { action, title: read('title'), icon: read('icon'), description: read('description'), layout: read('layout') }
}

const flatten = items => items.flatMap(item => [item, ...(item.children ? flatten(item.children) : [])])

const navIconByRoute = Object.fromEntries(
  flatten(navItems).filter(item => item.to?.name).map(item => [item.to.name, item.icon?.icon]),
)

const entries = Object.entries(pages).map(([path, source]) => ({ path, name: routeNameOf(path), meta: extractMeta(source) }))

describe('metadatos de ruta', () => {
  it('detecta las páginas con permiso', () => {
    expect(entries.filter(e => e.meta?.action).length).toBeGreaterThanOrEqual(9)
  })

  it.each(entries.filter(e => e.meta?.action).map(e => [e.name, e]))('%s declara title, icon y description', (_, { meta }) => {
    expect(meta.title).toBeTruthy()
    expect(meta.icon).toMatch(/^tabler-/)
    expect(meta.description).toBeTruthy()
  })

  it.each(entries.filter(e => navIconByRoute[e.name]).map(e => [e.name, e]))('%s usa el mismo ícono que el menú', (name, { meta }) => {
    expect(meta?.icon).toBe(navIconByRoute[name])
  })

  it('toda ruta autenticada con layout por defecto declara meta.title', () => {
    const missing = entries
      .filter(e => e.meta?.layout !== 'blank')
      .filter(e => !e.meta?.title)
      .map(e => e.path)

    expect(missing).toEqual([])
  })

  it('todo ítem del menú apunta a una página existente', () => {
    const names = new Set(entries.map(e => e.name))

    Object.keys(navIconByRoute).forEach(name => expect(names.has(name), name).toBe(true))
  })
})
