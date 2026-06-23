import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PipeController::upload
* @see app/Http/Controllers/Api/PipeController.php:142
* @route '/api/v1/pipe/{sessionId}/payload'
*/
export const upload = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: upload.url(args, options),
    method: 'put',
})

upload.definition = {
    methods: ["put"],
    url: '/api/v1/pipe/{sessionId}/payload',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\PipeController::upload
* @see app/Http/Controllers/Api/PipeController.php:142
* @route '/api/v1/pipe/{sessionId}/payload'
*/
upload.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return upload.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::upload
* @see app/Http/Controllers/Api/PipeController.php:142
* @route '/api/v1/pipe/{sessionId}/payload'
*/
upload.put = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: upload.url(args, options),
    method: 'put',
})

const payload = {
    upload: Object.assign(upload, upload),
}

export default payload