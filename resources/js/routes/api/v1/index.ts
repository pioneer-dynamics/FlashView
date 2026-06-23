import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import secrets from './secrets'
import webhook from './webhook'
import pipe from './pipe'
import calls from './calls'
/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
export const config = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(options),
    method: 'get',
})

config.definition = {
    methods: ["get","head"],
    url: '/api/v1/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
config.url = (options?: RouteQueryOptions) => {
    return config.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
config.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
config.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: config.url(options),
    method: 'head',
})

const v1 = {
    config: Object.assign(config, config),
    secrets: Object.assign(secrets, secrets),
    webhook: Object.assign(webhook, webhook),
    pipe: Object.assign(pipe, pipe),
    calls: Object.assign(calls, calls),
}

export default v1