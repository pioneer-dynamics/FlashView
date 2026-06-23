import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CliDeviceController::initiate
* @see app/Http/Controllers/CliDeviceController.php:25
* @route '/cli/device/initiate'
*/
export const initiate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

initiate.definition = {
    methods: ["post"],
    url: '/cli/device/initiate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliDeviceController::initiate
* @see app/Http/Controllers/CliDeviceController.php:25
* @route '/cli/device/initiate'
*/
initiate.url = (options?: RouteQueryOptions) => {
    return initiate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliDeviceController::initiate
* @see app/Http/Controllers/CliDeviceController.php:25
* @route '/cli/device/initiate'
*/
initiate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CliDeviceController::poll
* @see app/Http/Controllers/CliDeviceController.php:154
* @route '/cli/device/poll'
*/
export const poll = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: poll.url(options),
    method: 'get',
})

poll.definition = {
    methods: ["get","head"],
    url: '/cli/device/poll',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CliDeviceController::poll
* @see app/Http/Controllers/CliDeviceController.php:154
* @route '/cli/device/poll'
*/
poll.url = (options?: RouteQueryOptions) => {
    return poll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliDeviceController::poll
* @see app/Http/Controllers/CliDeviceController.php:154
* @route '/cli/device/poll'
*/
poll.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: poll.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CliDeviceController::poll
* @see app/Http/Controllers/CliDeviceController.php:154
* @route '/cli/device/poll'
*/
poll.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: poll.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CliDeviceController::show
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/cli/device',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CliDeviceController::show
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliDeviceController::show
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CliDeviceController::show
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CliDeviceController::activate
* @see app/Http/Controllers/CliDeviceController.php:82
* @route '/cli/device'
*/
export const activate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(options),
    method: 'post',
})

activate.definition = {
    methods: ["post"],
    url: '/cli/device',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliDeviceController::activate
* @see app/Http/Controllers/CliDeviceController.php:82
* @route '/cli/device'
*/
activate.url = (options?: RouteQueryOptions) => {
    return activate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliDeviceController::activate
* @see app/Http/Controllers/CliDeviceController.php:82
* @route '/cli/device'
*/
activate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(options),
    method: 'post',
})

const CliDeviceController = { initiate, poll, show, activate }

export default CliDeviceController