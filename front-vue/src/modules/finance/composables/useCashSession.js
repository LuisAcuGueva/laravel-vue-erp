import { storeToRefs } from 'pinia'
import { useCashStore } from '../stores/cashStore'

export function useCashSession() {
  const store = useCashStore()

  const {
    availableBoxes,
    selectedBoxId,
    selectedBox,
    currentSession,
    sessionSummary,
    hasOpenSession,
    isLoadingBoxes,
    isLoadingSession,
  } = storeToRefs(store)

  return {
    availableBoxes,
    selectedBoxId,
    selectedBox,
    currentSession,
    sessionSummary,
    hasOpenSession,
    isLoadingBoxes,
    isLoadingSession,

    fetchAvailableBoxes: () => store.fetchAvailableBoxes(),
    selectBox: boxId => store.selectBox(boxId),
    fetchCurrentSession: () => store.fetchCurrentSession(),
    openSession: payload => store.openSession(payload),
    closeSession: payload => store.closeSession(payload),
    addMovement: payload => store.addMovement(payload),
    cancelMovement: (movementId, reason) => store.cancelMovement(movementId, reason),
  }
}

export default useCashSession
