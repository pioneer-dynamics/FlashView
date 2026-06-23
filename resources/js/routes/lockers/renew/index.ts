import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
export const challenge = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

challenge.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/renew',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
challenge.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return challenge.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
challenge.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
challenge.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: challenge.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::purchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
export const purchase = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

purchase.definition = {
    methods: ["post"],
    url: '/lockers/{accountId}/renew',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::purchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
purchase.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return purchase.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::purchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
purchase.post = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

const renew = {
    challenge: Object.assign(challenge, challenge),
    purchase: Object.assign(purchase, purchase),
}

export default renew