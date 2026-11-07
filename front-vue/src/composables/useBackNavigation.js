/**
 * Botón "Volver": regresa a la página anterior del historial del navegador
 * (p. ej. Kardex o una venta que enlazó a este detalle) y solo cae al
 * listado indicado cuando no hay historial previo (URL directa, nueva pestaña).
 */
export function useBackNavigation(fallback) {
  const router = useRouter()

  const goBack = () => {
    if (window.history.state?.back) {
      router.back()
    } else {
      router.push(fallback)
    }
  }

  return { goBack }
}
