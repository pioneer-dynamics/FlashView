import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipePairingController::registerDevice
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
export const registerDevice = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerDevice.url(options),
    method: 'post',
})

registerDevice.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/devices',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::registerDevice
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
registerDevice.url = (options?: RouteQueryOptions) => {
    return registerDevice.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::registerDevice
* @see app/Http/Controllers/Api/PipePairingController.php:20
* @route '/api/v1/pipe/devices'
*/
registerDevice.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerDevice.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::waitingDevices
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
export const waitingDevices = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: waitingDevices.url(options),
    method: 'get',
})

waitingDevices.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices/waiting',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::waitingDevices
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waitingDevices.url = (options?: RouteQueryOptions) => {
    return waitingDevices.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::waitingDevices
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waitingDevices.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: waitingDevices.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::waitingDevices
* @see app/Http/Controllers/Api/PipePairingController.php:74
* @route '/api/v1/pipe/devices/waiting'
*/
waitingDevices.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: waitingDevices.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::listDevices
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
export const listDevices = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listDevices.url(options),
    method: 'get',
})

listDevices.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::listDevices
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
listDevices.url = (options?: RouteQueryOptions) => {
    return listDevices.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::listDevices
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
listDevices.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listDevices.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::listDevices
* @see app/Http/Controllers/Api/PipePairingController.php:44
* @route '/api/v1/pipe/devices'
*/
listDevices.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: listDevices.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::showDevice
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
export const showDevice = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showDevice.url(args, options),
    method: 'get',
})

showDevice.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/devices/{deviceId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::showDevice
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
showDevice.url = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return showDevice.definition.url
            .replace('{deviceId}', parsedArgs.deviceId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::showDevice
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
showDevice.get = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showDevice.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::showDevice
* @see app/Http/Controllers/Api/PipePairingController.php:57
* @route '/api/v1/pipe/devices/{deviceId}'
*/
showDevice.head = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showDevice.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroyDevice
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
export const destroyDevice = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyDevice.url(args, options),
    method: 'delete',
})

destroyDevice.definition = {
    methods: ["delete"],
    url: '/api/v1/pipe/devices/{deviceId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroyDevice
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
destroyDevice.url = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroyDevice.definition.url
            .replace('{deviceId}', parsedArgs.deviceId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::destroyDevice
* @see app/Http/Controllers/Api/PipePairingController.php:87
* @route '/api/v1/pipe/devices/{deviceId}'
*/
destroyDevice.delete = (args: { deviceId: string | number } | [deviceId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyDevice.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::sendSeed
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
export const sendSeed = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendSeed.url(options),
    method: 'post',
})

sendSeed.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/pairings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::sendSeed
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
sendSeed.url = (options?: RouteQueryOptions) => {
    return sendSeed.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::sendSeed
* @see app/Http/Controllers/Api/PipePairingController.php:101
* @route '/api/v1/pipe/pairings'
*/
sendSeed.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendSeed.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::pendingSeed
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
export const pendingSeed = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pendingSeed.url(options),
    method: 'get',
})

pendingSeed.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/pairings/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipePairingController::pendingSeed
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pendingSeed.url = (options?: RouteQueryOptions) => {
    return pendingSeed.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipePairingController::pendingSeed
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pendingSeed.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pendingSeed.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipePairingController::pendingSeed
* @see app/Http/Controllers/Api/PipePairingController.php:127
* @route '/api/v1/pipe/pairings/pending'
*/
pendingSeed.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pendingSeed.url(options),
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

const PipePairingController = { registerDevice, waitingDevices, listDevices, showDevice, destroyDevice, sendSeed, pendingSeed, show, accept }

export default PipePairingController