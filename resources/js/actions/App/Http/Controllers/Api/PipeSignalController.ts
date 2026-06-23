import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipeSignalController::store
* @see app/Http/Controllers/Api/PipeSignalController.php:17
* @route '/api/v1/pipe/{sessionId}/signal'
*/
export const store = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/{sessionId}/signal',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipeSignalController::store
* @see app/Http/Controllers/Api/PipeSignalController.php:17
* @route '/api/v1/pipe/{sessionId}/signal'
*/
store.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sessionId: args }
    }

    if (Array.isArray(args)) {
        args = {
            sessionId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sessionId: args.sessionId,
    }

    return store.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeSignalController::store
* @see app/Http/Controllers/Api/PipeSignalController.php:17
* @route '/api/v1/pipe/{sessionId}/signal'
*/
store.post = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipeSignalController::index
* @see app/Http/Controllers/Api/PipeSignalController.php:36
* @route '/api/v1/pipe/{sessionId}/signal'
*/
export const index = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/{sessionId}/signal',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipeSignalController::index
* @see app/Http/Controllers/Api/PipeSignalController.php:36
* @route '/api/v1/pipe/{sessionId}/signal'
*/
index.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sessionId: args }
    }

    if (Array.isArray(args)) {
        args = {
            sessionId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sessionId: args.sessionId,
    }

    return index.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeSignalController::index
* @see app/Http/Controllers/Api/PipeSignalController.php:36
* @route '/api/v1/pipe/{sessionId}/signal'
*/
index.get = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipeSignalController::index
* @see app/Http/Controllers/Api/PipeSignalController.php:36
* @route '/api/v1/pipe/{sessionId}/signal'
*/
index.head = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

const PipeSignalController = { store, index }

export default PipeSignalController