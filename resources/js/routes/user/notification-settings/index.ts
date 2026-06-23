import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationSettingsController::index
* @see app/Http/Controllers/NotificationSettingsController.php:10
* @route '/user/notification-settings'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/user/notification-settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\NotificationSettingsController::index
* @see app/Http/Controllers/NotificationSettingsController.php:10
* @route '/user/notification-settings'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationSettingsController::index
* @see app/Http/Controllers/NotificationSettingsController.php:10
* @route '/user/notification-settings'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\NotificationSettingsController::index
* @see app/Http/Controllers/NotificationSettingsController.php:10
* @route '/user/notification-settings'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const notificationSettings = {
    index: Object.assign(index, index),
}

export default notificationSettings