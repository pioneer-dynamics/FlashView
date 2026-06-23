import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import authorize3432b0 from './authorize'
import deviceD55896 from './device'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/cli',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
export const authorize = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

authorize.definition = {
    methods: ["get","head"],
    url: '/cli/authorize',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
authorize.url = (options?: RouteQueryOptions) => {
    return authorize.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
authorize.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
authorize.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authorize.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CliAuthController::token
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
export const token = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: token.url(options),
    method: 'post',
})

token.definition = {
    methods: ["post"],
    url: '/cli/token',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliAuthController::token
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
token.url = (options?: RouteQueryOptions) => {
    return token.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::token
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
token.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: token.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CliDeviceController::device
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
export const device = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: device.url(options),
    method: 'get',
})

device.definition = {
    methods: ["get","head"],
    url: '/cli/device',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CliDeviceController::device
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
device.url = (options?: RouteQueryOptions) => {
    return device.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliDeviceController::device
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
device.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: device.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CliDeviceController::device
* @see app/Http/Controllers/CliDeviceController.php:56
* @route '/cli/device'
*/
device.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: device.url(options),
    method: 'head',
})

const cli = {
    index: Object.assign(index, index),
    authorize: Object.assign(authorize, authorize3432b0),
    token: Object.assign(token, token),
    device: Object.assign(device, deviceD55896),
}

export default cli