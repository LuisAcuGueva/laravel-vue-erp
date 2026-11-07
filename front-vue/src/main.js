import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { themeConfig } from '@themeConfig'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'

document.title = themeConfig.app.title

// Create vue app
const app = createApp(App)


// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
