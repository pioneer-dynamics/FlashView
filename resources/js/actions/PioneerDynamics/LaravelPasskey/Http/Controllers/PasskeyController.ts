import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getAuthenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
export const getAuthenticationOptions = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: getAuthenticationOptions.url(options),
    method: 'post',
})

getAuthenticationOptions.definition = {
    methods: ["post"],
    url: '/passkeys/authentication-options',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getAuthenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
getAuthenticationOptions.url = (options?: RouteQueryOptions) => {
    return getAuthenticationOptions.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getAuthenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
getAuthenticationOptions.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: getAuthenticationOptions.url(options),
    method: 'post',
})

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::login
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:106
* @route '/passkeys/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

login.definition = {
    methods: ["post"],
    url: '/passkeys/login',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::login
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:106
* @route '/passkeys/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::login
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:106
* @route '/passkeys/login'
*/
login.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getRegistrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
export const getRegistrationOptions = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: getRegistrationOptions.url(options),
    method: 'post',
})

getRegistrationOptions.definition = {
    methods: ["post"],
    url: '/passkeys/registration-options',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getRegistrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
getRegistrationOptions.url = (options?: RouteQueryOptions) => {
    return getRegistrationOptions.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::getRegistrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
getRegistrationOptions.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: getRegistrationOptions.url(options),
    method: 'post',
})

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::verify
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:82
* @route '/passkeys/verify'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/passkeys/verify',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::verify
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:82
* @route '/passkeys/verify'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::verify
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:82
* @route '/passkeys/verify'
*/
verify.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::destroy
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:132
* @route '/passkeys/{passkey}'
*/
export const destroy = (args: { passkey: string | number } | [passkey: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/passkeys/{passkey}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::destroy
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:132
* @route '/passkeys/{passkey}'
*/
destroy.url = (args: { passkey: string | number } | [passkey: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { passkey: args }
    }

    if (Array.isArray(args)) {
        args = {
            passkey: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        passkey: args.passkey,
    }

    return destroy.definition.url
            .replace('{passkey}', parsedArgs.passkey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::destroy
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:132
* @route '/passkeys/{passkey}'
*/
destroy.delete = (args: { passkey: string | number } | [passkey: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::store
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:44
* @route '/passkeys'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/passkeys',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::store
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:44
* @route '/passkeys'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::store
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:44
* @route '/passkeys'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const PasskeyController = { getAuthenticationOptions, login, getRegistrationOptions, verify, destroy, store }

export default PasskeyController