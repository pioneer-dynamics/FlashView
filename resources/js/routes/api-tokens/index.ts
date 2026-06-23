import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::index
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:17
* @route '/user/api-tokens'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/user/api-tokens',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::index
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:17
* @route '/user/api-tokens'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::index
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:17
* @route '/user/api-tokens'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::index
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:17
* @route '/user/api-tokens'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::store
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:36
* @route '/user/api-tokens'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/user/api-tokens',
} satisfies RouteDefinition<["post"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::store
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:36
* @route '/user/api-tokens'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::store
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:36
* @route '/user/api-tokens'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::update
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:59
* @route '/user/api-tokens/{token}'
*/
export const update = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/user/api-tokens/{token}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::update
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:59
* @route '/user/api-tokens/{token}'
*/
update.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return update.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::update
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:59
* @route '/user/api-tokens/{token}'
*/
update.put = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:82
* @route '/user/api-tokens/{token}'
*/
export const destroy = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user/api-tokens/{token}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:82
* @route '/user/api-tokens/{token}'
*/
destroy.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return destroy.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/ApiTokenController.php:82
* @route '/user/api-tokens/{token}'
*/
destroy.delete = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const apiTokens = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default apiTokens