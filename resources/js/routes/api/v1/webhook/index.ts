import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WebhookController::show
* @see app/Http/Controllers/Api/WebhookController.php:13
* @route '/api/v1/webhook'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/webhook',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::show
* @see app/Http/Controllers/Api/WebhookController.php:13
* @route '/api/v1/webhook'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::show
* @see app/Http/Controllers/Api/WebhookController.php:13
* @route '/api/v1/webhook'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WebhookController::show
* @see app/Http/Controllers/Api/WebhookController.php:13
* @route '/api/v1/webhook'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\WebhookController::update
* @see app/Http/Controllers/Api/WebhookController.php:20
* @route '/api/v1/webhook'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/v1/webhook',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::update
* @see app/Http/Controllers/Api/WebhookController.php:20
* @route '/api/v1/webhook'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::update
* @see app/Http/Controllers/Api/WebhookController.php:20
* @route '/api/v1/webhook'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\WebhookController::regenerateSecret
* @see app/Http/Controllers/Api/WebhookController.php:41
* @route '/api/v1/webhook/regenerate-secret'
*/
export const regenerateSecret = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateSecret.url(options),
    method: 'post',
})

regenerateSecret.definition = {
    methods: ["post"],
    url: '/api/v1/webhook/regenerate-secret',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::regenerateSecret
* @see app/Http/Controllers/Api/WebhookController.php:41
* @route '/api/v1/webhook/regenerate-secret'
*/
regenerateSecret.url = (options?: RouteQueryOptions) => {
    return regenerateSecret.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::regenerateSecret
* @see app/Http/Controllers/Api/WebhookController.php:41
* @route '/api/v1/webhook/regenerate-secret'
*/
regenerateSecret.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateSecret.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WebhookController::destroy
* @see app/Http/Controllers/Api/WebhookController.php:57
* @route '/api/v1/webhook'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/webhook',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::destroy
* @see app/Http/Controllers/Api/WebhookController.php:57
* @route '/api/v1/webhook'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::destroy
* @see app/Http/Controllers/Api/WebhookController.php:57
* @route '/api/v1/webhook'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const webhook = {
    show: Object.assign(show, show),
    update: Object.assign(update, update),
    regenerateSecret: Object.assign(regenerateSecret, regenerateSecret),
    destroy: Object.assign(destroy, destroy),
}

export default webhook