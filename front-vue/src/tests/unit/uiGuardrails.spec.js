import { describe, expect, it } from 'vitest'

/**
 * Guardrail de UI (spec 031, design §8.10).
 * Excluye `src/@core/**` y `src/views/**` (código de la plantilla Vuexy): no entran en el glob.
 */
const sources = import.meta.glob('/src/{pages,modules,components,layouts}/**/*.vue', {
  query: '?raw',
  import: 'default',
  eager: true,
})

const SERVER_DATA_TABLE = '/src/components/common/ServerDataTable.vue'

const RULES = [
  {
    name: 'inputs App* (usar VTextField/VSelect/VAutocomplete/VTextarea/VCombobox con label)',
    pattern: /<App(TextField|Select|Autocomplete|Textarea|Combobox)\b/,
  },
  {
    name: 'VTable en páginas (usar ServerDataTable)',
    pattern: /<VTable\b/,
    only: path => path.startsWith('/src/pages/'),
  },
  {
    name: 'VDataTableServer fuera de ServerDataTable',
    pattern: /<VDataTableServer\b/,
    skip: path => path === SERVER_DATA_TABLE,
  },
  {
    name: 'diálogos de confirmación propios (usar useConfirm)',
    pattern: /\bConfirmDialog\b|window\.confirm\(|confirmColor/,
    skip: path => path === '/src/components/common/GlobalConfirmDialog.vue',
  },
  {
    name: 'items-length (usar :total-items)',
    pattern: /items-length=/,
    skip: path => path === SERVER_DATA_TABLE,
  },
  {
    name: 'RowActionButton con `type` (la prop es `action`: view | edit | delete | custom)',
    pattern: /<RowActionButton\b[^>]*\stype=/,
  },
  {
    name: 'errores de API leídos de `_data` (ofetch los expone en `err.data`; usar useApiFormErrors().setErrors(err))',
    pattern: /\._data\?*\.errors/,
  },
  {
    name: 'opción «Todos…» con value null (usar clearable)',
    pattern: /title:\s*'Tod[oa]s[^']*',\s*value:\s*null|value:\s*null,\s*title:\s*'Tod[oa]s/,
  },
]

describe('guardrails de UI', () => {
  it('encuentra los archivos a revisar', () => {
    expect(Object.keys(sources).length).toBeGreaterThan(20)
    expect(sources[SERVER_DATA_TABLE]).toBeDefined()
  })

  it.each(RULES.map(rule => [rule.name, rule]))('prohibido: %s', (_, rule) => {
    const offenders = Object.entries(sources)
      .filter(([path]) => (rule.only ? rule.only(path) : true) && !(rule.skip?.(path)))
      .filter(([, source]) => rule.pattern.test(source))
      .map(([path]) => path)

    expect(offenders).toEqual([])
  })
})
