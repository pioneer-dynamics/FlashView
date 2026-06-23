import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CallPageController::index
* @see app/Http/Controllers/CallPageController.php:11
* @route '/calls'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/calls',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CallPageController::index
* @see app/Http/Controllers/CallPageController.php:11
* @route '/calls'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallPageController::index
* @see app/Http/Controllers/CallPageController.php:11
* @route '/calls'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CallPageController::index
* @see app/Http/Controllers/CallPageController.php:11
* @route '/calls'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CallPageController::show
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
export const show = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/calls/{callSession}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CallPageController::show
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
show.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallPageController::show
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
show.get = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CallPageController::show
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
show.head = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CallPageController::room
* @see app/Http/Controllers/CallPageController.php:28
* @route '/calls/{callSession}/room'
*/
export const room = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: room.url(args, options),
    method: 'get',
})

room.definition = {
    methods: ["get","head"],
    url: '/calls/{callSession}/room',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CallPageController::room
* @see app/Http/Controllers/CallPageController.php:28
* @route '/calls/{callSession}/room'
*/
room.url = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return room.definition.url
            .replace('{callSession}', parsedArgs.callSession.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CallPageController::room
* @see app/Http/Controllers/CallPageController.php:28
* @route '/calls/{callSession}/room'
*/
room.get = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: room.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CallPageController::room
* @see app/Http/Controllers/CallPageController.php:28
* @route '/calls/{callSession}/room'
*/
room.head = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: room.url(args, options),
    method: 'head',
})

const CallPageController = { index, show, room }

export default CallPageController