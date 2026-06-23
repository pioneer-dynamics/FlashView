import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\SecretController::downloadFile
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
export const downloadFile = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
})

downloadFile.definition = {
    methods: ["get","head"],
    url: '/secret/{secret}/file',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecretController::downloadFile
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
downloadFile.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return downloadFile.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecretController::downloadFile
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
downloadFile.get = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecretController::downloadFile
* @see app/Http/Controllers/SecretController.php:167
* @route '/secret/{secret}/file'
*/
downloadFile.head = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: downloadFile.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecretController::confirmFileDownloaded
* @see app/Http/Controllers/SecretController.php:175
* @route '/secret/{secret}/file/downloaded'
*/
export const confirmFileDownloaded = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmFileDownloaded.url(args, options),
    method: 'post',
})

confirmFileDownloaded.definition = {
    methods: ["post"],
    url: '/secret/{secret}/file/downloaded',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SecretController::confirmFileDownloaded
* @see app/Http/Controllers/SecretController.php:175
* @route '/secret/{secret}/file/downloaded'
*/
confirmFileDownloaded.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return confirmFileDownloaded.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecretController::confirmFileDownloaded
* @see app/Http/Controllers/SecretController.php:175
* @route '/secret/{secret}/file/downloaded'
*/
confirmFileDownloaded.post = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmFileDownloaded.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SecretController::index
* @see app/Http/Controllers/SecretController.php:182
* @route '/secrets'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/secrets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecretController::index
* @see app/Http/Controllers/SecretController.php:182
* @route '/secrets'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecretController::index
* @see app/Http/Controllers/SecretController.php:182
* @route '/secrets'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecretController::index
* @see app/Http/Controllers/SecretController.php:182
* @route '/secrets'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecretController::destroy
* @see app/Http/Controllers/SecretController.php:193
* @route '/secrets/{secret}'
*/
export const destroy = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/secrets/{secret}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\SecretController::destroy
* @see app/Http/Controllers/SecretController.php:193
* @route '/secrets/{secret}'
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
* @see \App\Http\Controllers\SecretController::destroy
* @see app/Http/Controllers/SecretController.php:193
* @route '/secrets/{secret}'
*/
destroy.delete = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const SecretController = { store, show, decrypt, downloadFile, confirmFileDownloaded, index, destroy }

export default SecretController