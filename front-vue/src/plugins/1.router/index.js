import { setupLayouts } from 'virtual:meta-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { authGuard } from './authGuard'
import navItems from '@/navigation/vertical'
import { resolveNavRouteName } from '@/utils/navigation'

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])
    
    return route
  }
  
  return setupLayouts([route])[0]
}

// Las subpáginas (`/new`, `/[id]`) mantienen marcado en el menú el ítem del módulo al que pertenecen.
function assignNavActiveLink(route) {
  route.children?.forEach(assignNavActiveLink)

  const navActiveLink = route.meta?.navActiveLink ?? resolveNavRouteName(navItems, route.name)
  if (navActiveLink && navActiveLink !== route.name)
    route.meta = { ...route.meta, navActiveLink }

  return route
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(assignNavActiveLink(route))),
  ],
})

router.beforeEach(authGuard)

export { router }
export default function (app) {
  app.use(router)
}

