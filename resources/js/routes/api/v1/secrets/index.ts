import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import fileF7f338 from './file'
/**
* @see \App\Http\Controllers\Api\SecretController::index
* @see app/Http/Controllers/Api/SecretController.php:46
* @route '/api/v1/secrets'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/secrets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SecretController::index
* @see app/Http/Controllers/Api/SecretController.php:46
* @route '/api/v1/secrets'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::index
* @see app/Http/Controllers/Api/SecretController.php:46
* @route '/api/v1/secrets'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SecretController::index
* @see app/Http/Controllers/Api/SecretController.php:46
* @route '/api/v1/secrets'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\SecretController::store
* @see app/Http/Controllers/Api/SecretController.php:56
* @route '/api/v1/secrets'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/secrets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\SecretController::store
* @see app/Http/Controllers/Api/SecretController.php:56
* @route '/api/v1/secrets'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::store
* @see app/Http/Controllers/Api/SecretController.php:56
* @route '/api/v1/secrets'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\SecretController::show
* @see app/Http/Controllers/Api/SecretController.php:115
* @route '/api/v1/secrets/{secret}'
*/
export const show = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/secrets/{secret}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SecretController::show
* @see app/Http/Controllers/Api/SecretController.php:115
* @route '/api/v1/secrets/{secret}'
*/
show.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: args.secret,
    }

    return show.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::show
* @see app/Http/Controllers/Api/SecretController.php:115
* @route '/api/v1/secrets/{secret}'
*/
show.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SecretController::show
* @see app/Http/Controllers/Api/SecretController.php:115
* @route '/api/v1/secrets/{secret}'
*/
show.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\SecretController::destroy
* @see app/Http/Controllers/Api/SecretController.php:180
* @route '/api/v1/secrets/{secret}'
*/
export const destroy = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/secrets/{secret}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\SecretController::destroy
* @see app/Http/Controllers/Api/SecretController.php:180
* @route '/api/v1/secrets/{secret}'
*/
destroy.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: args.secret,
    }

    return destroy.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::destroy
* @see app/Http/Controllers/Api/SecretController.php:180
* @route '/api/v1/secrets/{secret}'
*/
destroy.delete = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\SecretController::retrieve
* @see app/Http/Controllers/Api/SecretController.php:125
* @route '/api/v1/secrets/{secret}/retrieve'
*/
export const retrieve = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: retrieve.url(args, options),
    method: 'get',
})

retrieve.definition = {
    methods: ["get","head"],
    url: '/api/v1/secrets/{secret}/retrieve',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SecretController::retrieve
* @see app/Http/Controllers/Api/SecretController.php:125
* @route '/api/v1/secrets/{secret}/retrieve'
*/
retrieve.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: args.secret,
    }

    return retrieve.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::retrieve
* @see app/Http/Controllers/Api/SecretController.php:125
* @route '/api/v1/secrets/{secret}/retrieve'
*/
retrieve.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: retrieve.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SecretController::retrieve
* @see app/Http/Controllers/Api/SecretController.php:125
* @route '/api/v1/secrets/{secret}/retrieve'
*/
retrieve.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: retrieve.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\SecretController::file
* @see app/Http/Controllers/Api/SecretController.php:162
* @route '/api/v1/secrets/{secret}/file'
*/
export const file = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: file.url(args, options),
    method: 'get',
})

file.definition = {
    methods: ["get","head"],
    url: '/api/v1/secrets/{secret}/file',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SecretController::file
* @see app/Http/Controllers/Api/SecretController.php:162
* @route '/api/v1/secrets/{secret}/file'
*/
file.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: args.secret,
    }

    return file.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::file
* @see app/Http/Controllers/Api/SecretController.php:162
* @route '/api/v1/secrets/{secret}/file'
*/
file.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: file.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SecretController::file
* @see app/Http/Controllers/Api/SecretController.php:162
* @route '/api/v1/secrets/{secret}/file'
*/
file.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: file.url(args, options),
    method: 'head',
})

const secrets = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    destroy: Object.assign(destroy, destroy),
    retrieve: Object.assign(retrieve, retrieve),
    file: Object.assign(file, fileF7f338),
}

export default secrets