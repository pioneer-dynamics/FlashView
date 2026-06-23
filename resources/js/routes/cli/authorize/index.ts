import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\CliAuthController::store
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/cli/authorize',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CliAuthController::store
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliAuthController::store
* @see app/Http/Controllers/CliAuthController.php:60
* @route '/cli/authorize'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const authorize = {
    store: Object.assign(store, store),
}

export default authorize