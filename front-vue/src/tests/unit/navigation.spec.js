import { describe, expect, it } from 'vitest'
import navItems from '@/navigation/vertical'
import { filterNavByAcl, findNavTrail, resolveNavRouteName } from '@/utils/navigation'

const flatten = items => items.flatMap(item => [item, ...(item.children ? flatten(item.children) : [])])

const canOnly = allowed => permission => !permission || allowed.includes(permission)

describe('navegación vertical', () => {
  it('todo ítem que no es heading declara un ícono explícito distinto de tabler-circle', () => {
    const items = flatten(navItems).filter(item => !item.heading)

    expect(items.length).toBeGreaterThan(0)
    items.forEach(item => {
      expect(item.icon?.icon, `«${item.title}» sin ícono`).toMatch(/^tabler-/)
      expect(item.icon.icon, `«${item.title}» usa el fallback`).not.toBe('tabler-circle')
    })
  })

  it('los íconos de los ítems son únicos', () => {
    const icons = flatten(navItems).filter(item => !item.heading).map(item => item.icon.icon)

    expect(new Set(icons).size).toBe(icons.length)
  })

  it('los headings están en mayúsculas y no llevan action', () => {
    navItems.filter(item => item.heading).forEach(item => {
      expect(item.heading).toBe(item.heading.toLocaleUpperCase('es'))
      expect(item.action).toBeUndefined()
    })
  })

  it('Inicio va primero y sin cabecera; no existe Second page', () => {
    expect(navItems[0].title).toBe('Inicio')
    expect(flatten(navItems).some(item => /second/i.test(item.title || ''))).toBe(false)
  })
})

describe('filterNavByAcl', () => {
  it('oculta la cabecera COMERCIAL si el usuario no ve Clientes', () => {
    const result = filterNavByAcl(navItems, canOnly(['users.view']))

    expect(result.some(item => item.heading === 'COMERCIAL')).toBe(false)
    expect(result.some(item => item.heading === 'MANTENIMIENTO')).toBe(true)
  })

  it('oculta grupos sin hijos visibles', () => {
    const result = filterNavByAcl(navItems, canOnly(['users.view']))
    const titles = result.map(item => item.title)

    expect(titles).toContain('Seguridad')
    expect(titles).not.toContain('Sedes')
    expect(result.find(item => item.title === 'Seguridad').children.map(c => c.title)).toEqual(['Usuarios'])
  })

  it('un ítem con lista de permisos se muestra si el usuario tiene cualquiera', () => {
    const result = filterNavByAcl(navItems, canOnly(['brands.view']))

    expect(result.some(item => item.heading === 'INVENTARIO')).toBe(true)
    expect(result.filter(item => !item.heading).map(item => item.title)).toEqual(['Inicio', 'Configuración'])
  })

  it('sin permisos solo queda Inicio', () => {
    const result = filterNavByAcl(navItems, canOnly([]))

    expect(result.map(item => item.title || item.heading)).toEqual(['Inicio'])
  })

  it('con todos los permisos conserva el árbol completo', () => {
    expect(filterNavByAcl(navItems, () => true)).toEqual(navItems)
  })
})

describe('findNavTrail', () => {
  it('devuelve grupo y link sin headings', () => {
    const trail = findNavTrail(navItems, 'users')

    expect(trail.map(c => c.title)).toEqual(['Seguridad', 'Usuarios'])
    expect(trail[0].to).toBeUndefined()
    expect(trail[1].to).toEqual({ name: 'users' })
  })

  it('devuelve el link directo', () => {
    expect(findNavTrail(navItems, 'audit').map(c => c.title)).toEqual(['Auditoría'])
  })

  it('devuelve vacío si la ruta no está en el menú', () => {
    expect(findNavTrail(navItems, 'profile')).toEqual([])
  })
})

describe('resolveNavRouteName', () => {
  it('una ruta del menú resuelve a sí misma', () => {
    expect(resolveNavRouteName(navItems, 'inventory-catalog')).toBe('inventory-catalog')
  })

  it('las subpáginas resuelven al ítem de su módulo', () => {
    expect(resolveNavRouteName(navItems, 'inventory-warehouse-notes-new')).toBe('inventory-warehouse-notes')
    expect(resolveNavRouteName(navItems, 'inventory-counts-id')).toBe('inventory-counts')
    expect(resolveNavRouteName(navItems, 'inventory-catalog-id')).toBe('inventory-catalog')
    expect(resolveNavRouteName(navItems, 'clients-id')).toBe('clients')
  })

  it('elige el prefijo más largo', () => {
    expect(resolveNavRouteName(navItems, 'finance-cash-sessions-id')).toBe('finance-cash-sessions')
  })

  it('no confunde prefijos parciales ni rutas fuera del menú', () => {
    expect(resolveNavRouteName(navItems, 'clientsx')).toBeNull()
    expect(resolveNavRouteName(navItems, 'profile')).toBeNull()
    expect(resolveNavRouteName(navItems, undefined)).toBeNull()
  })
})
