import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipeController::pending
* @see app/Http/Controllers/Api/PipeController.php:78
* @route '/api/v1/pipe/sessions/pending'
*/
export const pending = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

pending.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/sessions/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipeController::pending
* @see app/Http/Controllers/Api/PipeController.php:78
* @route '/api/v1/pipe/sessions/pending'
*/
pending.url = (options?: RouteQueryOptions) => {
    return pending.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::pending
* @see app/Http/Controllers/Api/PipeController.php:78
* @route '/api/v1/pipe/sessions/pending'
*/
pending.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipeController::pending
* @see app/Http/Controllers/Api/PipeController.php:78
* @route '/api/v1/pipe/sessions/pending'
*/
pending.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pending.url(options),
    method: 'head',
})

const sessions = {
    pending: Object.assign(pending, pending),
}

export default sessions