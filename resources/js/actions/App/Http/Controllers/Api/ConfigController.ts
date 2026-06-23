import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
const ConfigController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConfigController.url(options),
    method: 'get',
})

ConfigController.definition = {
    methods: ["get","head"],
    url: '/api/v1/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
ConfigController.url = (options?: RouteQueryOptions) => {
    return ConfigController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
ConfigController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConfigController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ConfigController::__invoke
* @see app/Http/Controllers/Api/ConfigController.php:14
* @route '/api/v1/config'
*/
ConfigController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ConfigController.url(options),
    method: 'head',
})

export default ConfigController