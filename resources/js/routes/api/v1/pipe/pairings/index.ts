import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/pairings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::pending
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
export const pending = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

pending.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/pairings/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::pending
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pending.url = (options?: RouteQueryOptions) => {
    return pending.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::pending
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pending.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::pending
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pending.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pending.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:155
* @route '/api/v1/pipe/pairings/{pairing}'
*/
export const show = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/pairings/{pairing}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:155
* @route '/api/v1/pipe/pairings/{pairing}'
*/
show.url = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pairing: args }
    }

    if (Array.isArray(args)) {
        args = {
            pairing: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        pairing: args.pairing,
    }

    return show.definition.url
            .replace('{pairing}', parsedArgs.pairing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:155
* @route '/api/v1/pipe/pairings/{pairing}'
*/
show.get = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:155
* @route '/api/v1/pipe/pairings/{pairing}'
*/
show.head = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::accept
* @see app/Http/Controllers/Api/PipePairingController.php:175
* @route '/api/v1/pipe/pairings/{pairing}/accept'
*/
export const accept = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/pairings/{pairing}/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::accept
* @see app/Http/Controllers/Api/PipePairingController.php:175
* @route '/api/v1/pipe/pairings/{pairing}/accept'
*/
accept.url = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pairing: args }
    }

    if (Array.isArray(args)) {
        args = {
            pairing: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        pairing: args.pairing,
    }

    return accept.definition.url
            .replace('{pairing}', parsedArgs.pairing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::accept
* @see app/Http/Controllers/Api/PipePairingController.php:175
* @route '/api/v1/pipe/pairings/{pairing}/accept'
*/
accept.post = (args: { pairing: string | number } | [pairing: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

const pairings = {
    store: Object.assign(store, store),
    pending: Object.assign(pending, pending),
    show: Object.assign(show, show),
    accept: Object.assign(accept, accept),
}

export default pairings