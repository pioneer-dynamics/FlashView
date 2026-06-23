import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::authenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
export const authenticationOptions = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticationOptions.url(options),
    method: 'post',
})

authenticationOptions.definition = {
    methods: ["post"],
    url: '/passkeys/authentication-options',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::authenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
authenticationOptions.url = (options?: RouteQueryOptions) => {
    return authenticationOptions.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::authenticationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:62
* @route '/passkeys/authentication-options'
*/
authenticationOptions.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticationOptions.url(options),
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
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::registrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
export const registrationOptions = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registrationOptions.url(options),
    method: 'post',
})

registrationOptions.definition = {
    methods: ["post"],
    url: '/passkeys/registration-options',
} satisfies RouteDefinition<["post"]>

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::registrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
registrationOptions.url = (options?: RouteQueryOptions) => {
    return registrationOptions.definition.url + queryParams(options)
}

/**
* @see \PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController::registrationOptions
* @see vendor/pioneer-dynamics/laravel-passkey/src/Http/Controllers/PasskeyController.php:34
* @route '/passkeys/registration-options'
*/
registrationOptions.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registrationOptions.url(options),
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

const passkeys = {
    authenticationOptions: Object.assign(authenticationOptions, authenticationOptions),
    login: Object.assign(login, login),
    registrationOptions: Object.assign(registrationOptions, registrationOptions),
    verify: Object.assign(verify, verify),
    destroy: Object.assign(destroy, destroy),
    store: Object.assign(store, store),
}

export default passkeys