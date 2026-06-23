import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationPreferencesController::update
* @see app/Http/Controllers/NotificationPreferencesController.php:10
* @route '/user/notification-preferences'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/user/notification-preferences',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\NotificationPreferencesController::update
* @see app/Http/Controllers/NotificationPreferencesController.php:10
* @route '/user/notification-preferences'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationPreferencesController::update
* @see app/Http/Controllers/NotificationPreferencesController.php:10
* @route '/user/notification-preferences'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const NotificationPreferencesController = { update }

export default NotificationPreferencesController