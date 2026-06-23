import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\WebhookSettingsController::update
* @see app/Http/Controllers/WebhookSettingsController.php:13
* @route '/user/webhook-settings'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/user/webhook-settings',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\WebhookSettingsController::update
* @see app/Http/Controllers/WebhookSettingsController.php:13
* @route '/user/webhook-settings'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WebhookSettingsController::update
* @see app/Http/Controllers/WebhookSettingsController.php:13
* @route '/user/webhook-settings'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\WebhookSettingsController::revealSecret
* @see app/Http/Controllers/WebhookSettingsController.php:31
* @route '/user/webhook-settings/reveal-secret'
*/
export const revealSecret = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: revealSecret.url(options),
    method: 'post',
})

revealSecret.definition = {
    methods: ["post"],
    url: '/user/webhook-settings/reveal-secret',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WebhookSettingsController::revealSecret
* @see app/Http/Controllers/WebhookSettingsController.php:31
* @route '/user/webhook-settings/reveal-secret'
*/
revealSecret.url = (options?: RouteQueryOptions) => {
    return revealSecret.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WebhookSettingsController::revealSecret
* @see app/Http/Controllers/WebhookSettingsController.php:31
* @route '/user/webhook-settings/reveal-secret'
*/
revealSecret.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: revealSecret.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\WebhookSettingsController::regenerateSecret
* @see app/Http/Controllers/WebhookSettingsController.php:43
* @route '/user/webhook-settings/regenerate-secret'
*/
export const regenerateSecret = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateSecret.url(options),
    method: 'post',
})

regenerateSecret.definition = {
    methods: ["post"],
    url: '/user/webhook-settings/regenerate-secret',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WebhookSettingsController::regenerateSecret
* @see app/Http/Controllers/WebhookSettingsController.php:43
* @route '/user/webhook-settings/regenerate-secret'
*/
regenerateSecret.url = (options?: RouteQueryOptions) => {
    return regenerateSecret.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WebhookSettingsController::regenerateSecret
* @see app/Http/Controllers/WebhookSettingsController.php:43
* @route '/user/webhook-settings/regenerate-secret'
*/
regenerateSecret.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: regenerateSecret.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\WebhookSettingsController::destroy
* @see app/Http/Controllers/WebhookSettingsController.php:59
* @route '/user/webhook-settings'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user/webhook-settings',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\WebhookSettingsController::destroy
* @see app/Http/Controllers/WebhookSettingsController.php:59
* @route '/user/webhook-settings'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WebhookSettingsController::destroy
* @see app/Http/Controllers/WebhookSettingsController.php:59
* @route '/user/webhook-settings'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\WebhookSettingsController::test
* @see app/Http/Controllers/WebhookSettingsController.php:73
* @route '/user/webhook-settings/test'
*/
export const test = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(options),
    method: 'post',
})

test.definition = {
    methods: ["post"],
    url: '/user/webhook-settings/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WebhookSettingsController::test
* @see app/Http/Controllers/WebhookSettingsController.php:73
* @route '/user/webhook-settings/test'
*/
test.url = (options?: RouteQueryOptions) => {
    return test.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WebhookSettingsController::test
* @see app/Http/Controllers/WebhookSettingsController.php:73
* @route '/user/webhook-settings/test'
*/
test.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(options),
    method: 'post',
})

const webhookSettings = {
    update: Object.assign(update, update),
    revealSecret: Object.assign(revealSecret, revealSecret),
    regenerateSecret: Object.assign(regenerateSecret, regenerateSecret),
    destroy: Object.assign(destroy, destroy),
    test: Object.assign(test, test),
}

export default webhookSettings