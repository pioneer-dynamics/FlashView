import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/api/v1/calls/{callSession}/join'
*/
const join17738f531f01046fb85f9fe2ad98eba3 = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join17738f531f01046fb85f9fe2ad98eba3.url(args, options),
    method: 'post',
})

join17738f531f01046fb85f9fe2ad98eba3.definition = {
    methods: ["post"],
    url: '/api/v1/calls/{callSession}/join',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/api/v1/calls/{callSession}/join'
*/
join17738f531f01046fb85f9fe2ad98eba3.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return join17738f531f01046fb85f9fe2ad98eba3.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/api/v1/calls/{callSession}/join'
*/
join17738f531f01046fb85f9fe2ad98eba3.post = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join17738f531f01046fb85f9fe2ad98eba3.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
const join72defb781d6a6068e2b311c9bb3fcd17 = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join72defb781d6a6068e2b311c9bb3fcd17.url(args, options),
    method: 'post',
})

join72defb781d6a6068e2b311c9bb3fcd17.definition = {
    methods: ["post"],
    url: '/call-sessions/{callSession}/join',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
join72defb781d6a6068e2b311c9bb3fcd17.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return join72defb781d6a6068e2b311c9bb3fcd17.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/call-sessions/{callSession}/join'
*/
join72defb781d6a6068e2b311c9bb3fcd17.post = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join72defb781d6a6068e2b311c9bb3fcd17.url(args, options),
    method: 'post',
})

/**
* Multiple routes resolve to \App\Http\Controllers\CallSessionController::join, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `join['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
export const join = {
    '/api/v1/calls/{callSession}/join': join17738f531f01046fb85f9fe2ad98eba3,
    '/call-sessions/{callSession}/join': join72defb781d6a6068e2b311c9bb3fcd17,
}

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

const CallSessionController = { join, challenge }

export default CallSessionController