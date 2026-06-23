import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ConfigurationController::index
* @see app/Http/Controllers/ConfigurationController.php:14
* @route '/user/settings'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/user/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConfigurationController::index
* @see app/Http/Controllers/ConfigurationController.php:14
* @route '/user/settings'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfigurationController::index
* @see app/Http/Controllers/ConfigurationController.php:14
* @route '/user/settings'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ConfigurationController::index
* @see app/Http/Controllers/ConfigurationController.php:14
* @route '/user/settings'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ConfigurationController::update
* @see app/Http/Controllers/ConfigurationController.php:25
* @route '/user/settings'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/user/settings',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ConfigurationController::update
* @see app/Http/Controllers/ConfigurationController.php:25
* @route '/user/settings'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfigurationController::update
* @see app/Http/Controllers/ConfigurationController.php:25
* @route '/user/settings'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const settings = {
    index: Object.assign(index, index),
    update: Object.assign(update, update),
}

export default settings