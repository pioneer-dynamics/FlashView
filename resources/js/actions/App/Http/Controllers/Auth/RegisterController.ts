import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\RegisterController::create
* @see app/Http/Controllers/Auth/RegisterController.php:31
* @route '/register'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/register',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::create
* @see app/Http/Controllers/Auth/RegisterController.php:31
* @route '/register'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::create
* @see app/Http/Controllers/Auth/RegisterController.php:31
* @route '/register'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::create
* @see app/Http/Controllers/Auth/RegisterController.php:31
* @route '/register'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:39
* @route '/register'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/register',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:39
* @route '/register'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:39
* @route '/register'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::success
* @see app/Http/Controllers/Auth/RegisterController.php:66
* @route '/register/success'
*/
export const success = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: success.url(options),
    method: 'get',
})

success.definition = {
    methods: ["get","head"],
    url: '/register/success',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::success
* @see app/Http/Controllers/Auth/RegisterController.php:66
* @route '/register/success'
*/
success.url = (options?: RouteQueryOptions) => {
    return success.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::success
* @see app/Http/Controllers/Auth/RegisterController.php:66
* @route '/register/success'
*/
success.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: success.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::success
* @see app/Http/Controllers/Auth/RegisterController.php:66
* @route '/register/success'
*/
success.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: success.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::complete
* @see app/Http/Controllers/Auth/RegisterController.php:75
* @route '/register/complete'
*/
export const complete = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: complete.url(options),
    method: 'get',
})

complete.definition = {
    methods: ["get","head"],
    url: '/register/complete',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::complete
* @see app/Http/Controllers/Auth/RegisterController.php:75
* @route '/register/complete'
*/
complete.url = (options?: RouteQueryOptions) => {
    return complete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::complete
* @see app/Http/Controllers/Auth/RegisterController.php:75
* @route '/register/complete'
*/
complete.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: complete.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::complete
* @see app/Http/Controllers/Auth/RegisterController.php:75
* @route '/register/complete'
*/
complete.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: complete.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::storeComplete
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
export const storeComplete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeComplete.url(options),
    method: 'post',
})

storeComplete.definition = {
    methods: ["post"],
    url: '/register/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::storeComplete
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
storeComplete.url = (options?: RouteQueryOptions) => {
    return storeComplete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::storeComplete
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
storeComplete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeComplete.url(options),
    method: 'post',
})

const RegisterController = { create, store, success, complete, storeComplete }

export default RegisterController