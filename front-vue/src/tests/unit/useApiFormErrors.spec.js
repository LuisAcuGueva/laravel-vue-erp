import { describe, expect, it } from 'vitest'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

describe('useApiFormErrors composable', () => {
  it('inicializa con estado vacio', () => {
    const { errors, generalError, hasErrors } = useApiFormErrors()

    expect(errors.value).toEqual({})
    expect(generalError.value).toBeNull()
    expect(hasErrors()).toBe(false)
  })

  it('procesa errores de validacion 422 de Laravel', () => {
    const { errors, generalError, setErrors, hasErrors } = useApiFormErrors()

    const apiErrorResponse = {
      message: 'Los datos proporcionados no son válidos.',
      errors: {
        name: ['El nombre es obligatorio.'],
        email: ['El correo ya está en uso.', 'Formato no válido.'],
      },
    }

    setErrors(apiErrorResponse)

    expect(errors.value.name).toBe('El nombre es obligatorio.')
    expect(errors.value.email).toBe('El correo ya está en uso.')
    expect(generalError.value).toBe('Los datos proporcionados no son válidos.')
    expect(hasErrors()).toBe(true)
  })

  it('procesa errores anidados en error.data o error.response._data', () => {
    const { errors, generalError, setErrors } = useApiFormErrors()

    const nestedError = {
      data: {
        message: 'Error en la petición.',
        errors: {
          description: ['La descripción excede el límite permitido.'],
        },
      },
    }

    setErrors(nestedError)

    expect(errors.value.description).toBe('La descripción excede el límite permitido.')
    expect(generalError.value).toBe('Error en la petición.')
  })

  it('limpia el error de un campo especifico con clearFieldError', () => {
    const { errors, setErrors, clearFieldError } = useApiFormErrors()

    setErrors({
      errors: {
        name: ['Obligatorio'],
        email: ['Invalido'],
      },
    })

    expect(errors.value.name).toBe('Obligatorio')
    expect(errors.value.email).toBe('Invalido')

    clearFieldError('name')

    expect(errors.value.name).toBeUndefined()
    expect(errors.value.email).toBe('Invalido')
  })

  it('limpia todos los errores con clearErrors', () => {
    const { errors, generalError, setErrors, clearErrors, hasErrors } = useApiFormErrors()

    setErrors({
      message: 'Error general',
      errors: {
        name: ['Obligatorio'],
      },
    })

    expect(hasErrors()).toBe(true)

    clearErrors()

    expect(errors.value).toEqual({})
    expect(generalError.value).toBeNull()
    expect(hasErrors()).toBe(false)
  })
})
