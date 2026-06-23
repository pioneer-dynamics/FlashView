import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/OtherBrowserSessionsController.php:21
* @route '/user/other-browser-sessions'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user/other-browser-sessions',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/OtherBrowserSessionsController.php:21
* @route '/user/other-browser-sessions'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController::destroy
* @see vendor/laravel/jetstream/src/Http/Controllers/Inertia/OtherBrowserSessionsController.php:21
* @route '/user/other-browser-sessions'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const OtherBrowserSessionsController = { destroy }

export default OtherBrowserSessionsController