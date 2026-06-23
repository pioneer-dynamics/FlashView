import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import signal from './signal'
/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/api/v1/calls/{callSession}/join'
*/
export const join = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(args, options),
    method: 'post',
})

join.definition = {
    methods: ["post"],
    url: '/api/v1/calls/{callSession}/join',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CallSessionController::join
* @see app/Http/Controllers/CallSessionController.php:24
* @route '/api/v1/calls/{callSession}/join'
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
* @route '/api/v1/calls/{callSession}/join'
*/
join.post = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CallSignalController::participants
* @see app/Http/Controllers/Api/CallSignalController.php:16
* @route '/api/v1/calls/{callSession}/participants'
*/
export const participants = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: participants.url(args, options),
    method: 'get',
})

participants.definition = {
    methods: ["get","head"],
    url: '/api/v1/calls/{callSession}/participants',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CallSignalController::participants
* @see app/Http/Controllers/Api/CallSignalController.php:16
* @route '/api/v1/calls/{callSession}/participants'
*/
participants.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return participants.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CallSignalController::participants
* @see app/Http/Controllers/Api/CallSignalController.php:16
* @route '/api/v1/calls/{callSession}/participants'
*/
participants.get = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: participants.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CallSignalController::participants
* @see app/Http/Controllers/Api/CallSignalController.php:16
* @route '/api/v1/calls/{callSession}/participants'
*/
participants.head = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: participants.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CallSignalController::leave
* @see app/Http/Controllers/Api/CallSignalController.php:58
* @route '/api/v1/calls/{callSession}/leave'
*/
export const leave = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(args, options),
    method: 'post',
})

leave.definition = {
    methods: ["post"],
    url: '/api/v1/calls/{callSession}/leave',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CallSignalController::leave
* @see app/Http/Controllers/Api/CallSignalController.php:58
* @route '/api/v1/calls/{callSession}/leave'
*/
leave.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return leave.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CallSignalController::leave
* @see app/Http/Controllers/Api/CallSignalController.php:58
* @route '/api/v1/calls/{callSession}/leave'
*/
leave.post = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(args, options),
    method: 'post',
})

const calls = {
    join: Object.assign(join, join),
    participants: Object.assign(participants, participants),
    signal: Object.assign(signal, signal),
    leave: Object.assign(leave, leave),
}

export default calls