// Ambient declarations for the Ziggy route() global, injected by ZiggyVue plugin.
// Only used for route().current() active-route checks — URL generation uses Wayfinder.
import type { Router, Config } from '../../vendor/tightenco/ziggy/src/js/index.d.ts'

declare global {
    function route(): Router
    function route(name: string, params?: unknown, absolute?: boolean, config?: Config): string
}
