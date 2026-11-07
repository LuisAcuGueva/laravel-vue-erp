// Espejo de App\Modules\Finance\Support\AccountCode y ConceptDirection::allowsAccount().
export const ACCOUNT_CODE_PATTERN = /^\d{2,8}$/

export const ACCOUNT_CODE_MESSAGE = 'El código debe tener entre 2 y 8 dígitos, sin puntos ni espacios.'

/**
 * Una cuenta raíz tiene 2 dígitos; una hija empieza con el código de su padre y es más larga.
 */
export const isValidAccountCode = (code, parentCode = null) => {
  if (!ACCOUNT_CODE_PATTERN.test(code ?? ''))
    return false

  return parentCode
    ? code.startsWith(parentCode) && code.length > parentCode.length
    : code.length === 2
}

/**
 * Mensaje de validación del código según el padre elegido, o `true` si es válido.
 */
export const accountCodeRule = (code, parentCode = null) => {
  if (!ACCOUNT_CODE_PATTERN.test(code ?? ''))
    return ACCOUNT_CODE_MESSAGE

  if (isValidAccountCode(code, parentCode))
    return true

  return parentCode
    ? `El código debe comenzar con el código de la cuenta padre (${parentCode}).`
    : 'Una cuenta principal debe tener 2 dígitos.'
}

// Matriz dirección × tipo de cuenta (spec 032 §4.5).
export const directionAllowsAccount = (direction, account) => {
  const type = account?.type?.value ?? account?.type

  if (direction === 'income')
    return type !== 'expense'
  if (direction === 'expense')
    return ['asset', 'liability', 'expense'].includes(type)
  if (direction === 'transfer')
    return type === 'asset' && String(account.code).startsWith('10')

  return false
}
