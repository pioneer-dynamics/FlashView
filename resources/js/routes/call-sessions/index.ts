import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\CallSessionController::challenge
* @see app/Http/Controllers/CallSessionController.php:13
* @route '/call-sessions/{callSession}/challenge'
*/
export const challenge = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

challenge.definition = {
    methods: ["get","head"],
    url: '/call-sessions/{callSession}/challenge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CallSessionController::challenge
* @see app/Http/Controllers/CallSessionController.php:13
* @route '/call-sessions/{callSession}/challenge'
*/
challenge.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { callSession: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { callSession: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            callSession: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        callSession: typeof args.callSession === 'object'
        ? args.callSession.id
        : args.callSession,
    }

    return challenge.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallSessionController::challenge
* @see app/Http/Controllers/CallSessionController.php:13
* @route '/call-sessions/{callSession}/challenge'
*/
challenge.get = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CallSessionController::challenge
* @see app/Http/Controllers/CallSessionController.php:13
* @route '/call-sessions/{callSession}/challenge'
*/
challenge.head = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: challenge.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
export const join = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(args, options),
    method: 'post',
})

join.definition = {
    methods: ["post"],
    url: '/call-sessions/{callSession}/join',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
join.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { callSession: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { callSession: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            callSession: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        callSession: typeof args.callSession === 'object'
        ? args.callSession.id
        : args.callSession,
    }

    return join.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
join.post = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(args, options),
    method: 'post',
})

const callSessions = {
    challenge: Object.assign(challenge, challenge),
    join: Object.assign(join, join),
}

export default callSessions