import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import devices from './devices'
import pairings from './pairings'
import sessions from './sessions'
import payload from './payload'
import signal from './signal'
/**
* @see \App\Http\Controllers\Api\PipeController::store
* @see app/Http/Controllers/Api/PipeController.php:19
* @route '/api/v1/pipe'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/pipe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipeController::store
* @see app/Http/Controllers/Api/PipeController.php:19
* @route '/api/v1/pipe'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::store
* @see app/Http/Controllers/Api/PipeController.php:19
* @route '/api/v1/pipe'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipeController::show
* @see app/Http/Controllers/Api/PipeController.php:61
* @route '/api/v1/pipe/{sessionId}'
*/
export const show = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/{sessionId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipeController::show
* @see app/Http/Controllers/Api/PipeController.php:61
* @route '/api/v1/pipe/{sessionId}'
*/
show.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::show
* @see app/Http/Controllers/Api/PipeController.php:61
* @route '/api/v1/pipe/{sessionId}'
*/
show.get = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipeController::show
* @see app/Http/Controllers/Api/PipeController.php:61
* @route '/api/v1/pipe/{sessionId}'
*/
show.head = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipeController::prepareUpload
* @see app/Http/Controllers/Api/PipeController.php:108
* @route '/api/v1/pipe/{sessionId}/prepare-upload'
*/
export const prepareUpload = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareUpload.url(args, options),
    method: 'post',
})

prepareUpload.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/{sessionId}/prepare-upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipeController::prepareUpload
* @see app/Http/Controllers/Api/PipeController.php:108
* @route '/api/v1/pipe/{sessionId}/prepare-upload'
*/
prepareUpload.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return prepareUpload.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::prepareUpload
* @see app/Http/Controllers/Api/PipeController.php:108
* @route '/api/v1/pipe/{sessionId}/prepare-upload'
*/
prepareUpload.post = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareUpload.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipeController::complete
* @see app/Http/Controllers/Api/PipeController.php:159
* @route '/api/v1/pipe/{sessionId}/complete'
*/
export const complete = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/api/v1/pipe/{sessionId}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PipeController::complete
* @see app/Http/Controllers/Api/PipeController.php:159
* @route '/api/v1/pipe/{sessionId}/complete'
*/
complete.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return complete.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::complete
* @see app/Http/Controllers/Api/PipeController.php:159
* @route '/api/v1/pipe/{sessionId}/complete'
*/
complete.post = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PipeController::download
* @see app/Http/Controllers/Api/PipeController.php:180
* @route '/api/v1/pipe/{sessionId}/download'
*/
export const download = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/api/v1/pipe/{sessionId}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PipeController::download
* @see app/Http/Controllers/Api/PipeController.php:180
* @route '/api/v1/pipe/{sessionId}/download'
*/
download.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return download.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::download
* @see app/Http/Controllers/Api/PipeController.php:180
* @route '/api/v1/pipe/{sessionId}/download'
*/
download.get = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PipeController::download
* @see app/Http/Controllers/Api/PipeController.php:180
* @route '/api/v1/pipe/{sessionId}/download'
*/
download.head = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PipeController::destroy
* @see app/Http/Controllers/Api/PipeController.php:203
* @route '/api/v1/pipe/{sessionId}'
*/
export const destroy = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/pipe/{sessionId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\PipeController::destroy
* @see app/Http/Controllers/Api/PipeController.php:203
* @route '/api/v1/pipe/{sessionId}'
*/
destroy.url = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{sessionId}', parsedArgs.sessionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PipeController::destroy
* @see app/Http/Controllers/Api/PipeController.php:203
* @route '/api/v1/pipe/{sessionId}'
*/
destroy.delete = (args: { sessionId: string | number } | [sessionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const pipe = {
    devices: Object.assign(devices, devices),
    pairings: Object.assign(pairings, pairings),
    sessions: Object.assign(sessions, sessions),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    prepareUpload: Object.assign(prepareUpload, prepareUpload),
    payload: Object.assign(payload, payload),
    complete: Object.assign(complete, complete),
    download: Object.assign(download, download),
    destroy: Object.assign(destroy, destroy),
    signal: Object.assign(signal, signal),
}

export default pipe