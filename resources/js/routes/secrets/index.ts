import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
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

const secrets = {
    index: Object.assign(index, index),
    destroy: Object.assign(destroy, destroy),
}

export default secrets