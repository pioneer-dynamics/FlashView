import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\CurrentUserController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/CurrentUserController.php:22
* @route '/user'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\CurrentUserController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/CurrentUserController.php:22
* @route '/user'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\CurrentUserController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/CurrentUserController.php:22
* @route '/user'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const CurrentUserController = { destroy }

export default CurrentUserController