import '../css/app.css'
import './bootstrap'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { initializeDarkMode } from '@/Composables/useDarkMode'
import { ZiggyVue } from '../../vendor/tightenco/ziggy' // ← tambah ini
import { Ziggy } from './ziggy'

if (typeof window !== 'undefined') {
  window.Ziggy = Ziggy
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

initializeDarkMode()

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.vue`,
      import.meta.glob('./Pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue, Ziggy) // ← tambah ini
      .mount(el)
  },
  progress: {
    color: '#4B5563',
  },
})
