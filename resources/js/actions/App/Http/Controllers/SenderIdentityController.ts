import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SenderIdentityController::store
* @see app/Http/Controllers/SenderIdentityController.php:20
* @route '/user/sender-identity'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/user/sender-identity',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SenderIdentityController::store
* @see app/Http/Controllers/SenderIdentityController.php:20
* @route '/user/sender-identity'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SenderIdentityController::store
* @see app/Http/Controllers/SenderIdentityController.php:20
* @route '/user/sender-identity'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SenderIdentityController::verify
* @see app/Http/Controllers/SenderIdentityController.php:81
* @route '/user/sender-identity/verify'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/user/sender-identity/verify',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SenderIdentityController::verify
* @see app/Http/Controllers/SenderIdentityController.php:81
* @route '/user/sender-identity/verify'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SenderIdentityController::verify
* @see app/Http/Controllers/SenderIdentityController.php:81
* @route '/user/sender-identity/verify'
*/
verify.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SenderIdentityController::destroy
* @see app/Http/Controllers/SenderIdentityController.php:113
* @route '/user/sender-identity'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user/sender-identity',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\SenderIdentityController::destroy
* @see app/Http/Controllers/SenderIdentityController.php:113
* @route '/user/sender-identity'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SenderIdentityController::destroy
* @see app/Http/Controllers/SenderIdentityController.php:113
* @route '/user/sender-identity'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const SenderIdentityController = { store, verify, destroy }

export default SenderIdentityController