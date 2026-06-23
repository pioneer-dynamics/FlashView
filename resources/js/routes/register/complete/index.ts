import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/register/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:93
* @route '/register/complete'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const complete = {
    store: Object.assign(store, store),
}

export default complete