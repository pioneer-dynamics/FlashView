import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CliAuthController::show
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/cli/authorize',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CliAuthController::show
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::show
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CliAuthController::show
* @see app/Http/Controllers/CliAuthController.php:24
* @route '/cli/authorize'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
export const authorize = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authorize.url(options),
    method: 'post',
})

authorize.definition = {
    methods: ["post"],
    url: '/cli/authorize',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
authorize.url = (options?: RouteQueryOptions) => {
    return authorize.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::authorize
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
authorize.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authorize.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CliAuthController::exchangeToken
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
export const exchangeToken = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: exchangeToken.url(options),
    method: 'post',
})

exchangeToken.definition = {
    methods: ["post"],
    url: '/cli/token',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliAuthController::exchangeToken
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
exchangeToken.url = (options?: RouteQueryOptions) => {
    return exchangeToken.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::exchangeToken
* @see app/Http/Controllers/CliAuthController.php:103
* @route '/cli/token'
*/
exchangeToken.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: exchangeToken.url(options),
    method: 'post',
})

const CliAuthController = { show, authorize, exchangeToken }

export default CliAuthController