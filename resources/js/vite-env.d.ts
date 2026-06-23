/// <reference types="vite/client" />

import type { Router, Config } from '../../vendor/tightenco/ziggy/src/js/index.d.ts'
import type { Auth, Flash, SecretsConfig, SupportConfig, JetstreamConfig } from './types'
import type { AxiosStatic } from 'axios'

// luxon ships plain JS source with no bundled .d.ts files.
// This ambient declaration silences the implicit-any error.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare module 'luxon'

// Teach Inertia about our application's shared page props so that
// $page.props and usePage().props are fully typed throughout the app.
declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            auth: Auth
            flash: Flash
            config: {
                app: { name: string }
                secrets: SecretsConfig
                support: SupportConfig
                access: { enabled: boolean }
            }
            ziggy: unknown
            jetstream: JetstreamConfig
        }
    }
}

// Make the Ziggy route() helper available in Vue templates.
declare module 'vue' {
    interface ComponentCustomProperties {
        route: {
            (): Router
            (name: string, params?: unknown, absolute?: boolean, config?: Config): string
        }
    }
}

// Make axios available as a global (window.axios = axios in bootstrap.js).
declare global {
    const axios: AxiosStatic
}
