import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import fileF7f338 from './file'
/**
* @see \App\Http\Controllers\SecretController::store
* @see app/Http/Controllers/SecretController.php:50
* @route '/secret'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/secret',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SecretController::store
* @see app/Http/Controllers/SecretController.php:50
* @route '/secret'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecretController::store
* @see app/Http/Controllers/SecretController.php:50
* @route '/secret'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SecretController::show
* @see app/Http/Controllers/SecretController.php:117
* @route '/secret/{secret}'
*/
export const show = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/secret/{secret}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecretController::show
* @see app/Http/Controllers/SecretController.php:117
* @route '/secret/{secret}'
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
* @see \App\Http\Controllers\SecretController::show
* @see app/Http/Controllers/SecretController.php:117
* @route '/secret/{secret}'
*/
show.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecretController::show
* @see app/Http/Controllers/SecretController.php:117
* @route '/secret/{secret}'
*/
show.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecretController::decrypt
* @see app/Http/Controllers/SecretController.php:140
* @route '/secret/{secret}/decrypt'
*/
export const decrypt = (args: { secret: string | number | { id: string | number } } | [secret: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: decrypt.url(args, options),
    method: 'get',
})

decrypt.definition = {
    methods: ["get","head"],
    url: '/secret/{secret}/decrypt',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecretController::decrypt
* @see app/Http/Controllers/SecretController.php:140
* @route '/secret/{secret}/decrypt'
*/
decrypt.url = (args: { secret: string | number | { id: string | number } } | [secret: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { secret: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: typeof args.secret === 'object'
        ? args.secret.id
        : args.secret,
    }

    return decrypt.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecretController::decrypt
* @see app/Http/Controllers/SecretController.php:140
* @route '/secret/{secret}/decrypt'
*/
decrypt.get = (args: { secret: string | number | { id: string | number } } | [secret: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: decrypt.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecretController::decrypt
* @see app/Http/Controllers/SecretController.php:140
* @route '/secret/{secret}/decrypt'
*/
decrypt.head = (args: { secret: string | number | { id: string | number } } | [secret: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: decrypt.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecretController::file
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
export const file = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: file.url(args, options),
    method: 'get',
})

file.definition = {
    methods: ["get","head"],
    url: '/secret/{secret}/file',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecretController::file
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
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
* @see \App\Http\Controllers\SecretController::file
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
file.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: file.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecretController::file
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
file.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: file.url(args, options),
    method: 'head',
})

const secret = {
    file: Object.assign(file, fileF7f338),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    decrypt: Object.assign(decrypt, decrypt),
}

export default secret