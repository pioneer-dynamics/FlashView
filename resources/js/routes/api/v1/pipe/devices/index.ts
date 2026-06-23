import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/devices',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::store
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::waiting
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
export const waiting = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: waiting.url(options),
    method: 'get',
})

waiting.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices/waiting',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::waiting
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waiting.url = (options?: RouteQueryOptions) => {
    return waiting.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::waiting
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waiting.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: waiting.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::waiting
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waiting.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: waiting.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::index
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::index
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::index
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::index
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
export const show = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices/{deviceId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
show.url = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { deviceId: args }
    }

    if (Array.isArray(args)) {
        args = {
            deviceId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        deviceId: args.deviceId,
    }

    return show.definition.url
            .replace('{deviceId}', parsedArgs.deviceId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
show.get = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::show
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
show.head = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroy
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
export const destroy = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/pipe/devices/{deviceId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroy
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
destroy.url = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { deviceId: args }
    }

    if (Array.isArray(args)) {
        args = {
            deviceId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        deviceId: args.deviceId,
    }

    return destroy.definition.url
            .replace('{deviceId}', parsedArgs.deviceId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroy
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
destroy.delete = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const devices = {
    store: Object.assign(store, store),
    waiting: Object.assign(waiting, waiting),
    index: Object.assign(index, index),
    show: Object.assign(show, show),
    destroy: Object.assign(destroy, destroy),
}

export default devices