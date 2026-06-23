import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
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
* @see \App\Http\Controllers\SecureLineCheckoutController::buy
* @see app/Http/Controllers/SecureLineCheckoutController.php:23
* @route '/calls/buy'
*/
export const buy = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buy.url(options),
    method: 'get',
})

buy.definition = {
    methods: ["get","head"],
    url: '/calls/buy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::buy
* @see app/Http/Controllers/SecureLineCheckoutController.php:23
* @route '/calls/buy'
*/
buy.url = (options?: RouteQueryOptions) => {
    return buy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::buy
* @see app/Http/Controllers/SecureLineCheckoutController.php:23
* @route '/calls/buy'
*/
buy.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buy.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::buy
* @see app/Http/Controllers/SecureLineCheckoutController.php:23
* @route '/calls/buy'
*/
buy.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: buy.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::checkout
* @see app/Http/Controllers/SecureLineCheckoutController.php:33
* @route '/calls/checkout'
*/
export const checkout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(options),
    method: 'post',
})

checkout.definition = {
    methods: ["post"],
    url: '/calls/checkout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::checkout
* @see app/Http/Controllers/SecureLineCheckoutController.php:33
* @route '/calls/checkout'
*/
checkout.url = (options?: RouteQueryOptions) => {
    return checkout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::checkout
* @see app/Http/Controllers/SecureLineCheckoutController.php:33
* @route '/calls/checkout'
*/
checkout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::awaitCredit
* @see app/Http/Controllers/SecureLineCheckoutController.php:64
* @route '/calls/await-credit'
*/
export const awaitCredit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: awaitCredit.url(options),
    method: 'get',
})

awaitCredit.definition = {
    methods: ["get","head"],
    url: '/calls/await-credit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::awaitCredit
* @see app/Http/Controllers/SecureLineCheckoutController.php:64
* @route '/calls/await-credit'
*/
awaitCredit.url = (options?: RouteQueryOptions) => {
    return awaitCredit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::awaitCredit
* @see app/Http/Controllers/SecureLineCheckoutController.php:64
* @route '/calls/await-credit'
*/
awaitCredit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: awaitCredit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::awaitCredit
* @see app/Http/Controllers/SecureLineCheckoutController.php:64
* @route '/calls/await-credit'
*/
awaitCredit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: awaitCredit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::creditStatus
* @see app/Http/Controllers/SecureLineCheckoutController.php:71
* @route '/calls/credit-status'
*/
export const creditStatus = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: creditStatus.url(options),
    method: 'get',
})

creditStatus.definition = {
    methods: ["get","head"],
    url: '/calls/credit-status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::creditStatus
* @see app/Http/Controllers/SecureLineCheckoutController.php:71
* @route '/calls/credit-status'
*/
creditStatus.url = (options?: RouteQueryOptions) => {
    return creditStatus.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::creditStatus
* @see app/Http/Controllers/SecureLineCheckoutController.php:71
* @route '/calls/credit-status'
*/
creditStatus.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: creditStatus.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::creditStatus
* @see app/Http/Controllers/SecureLineCheckoutController.php:71
* @route '/calls/credit-status'
*/
creditStatus.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: creditStatus.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::create
* @see app/Http/Controllers/SecureLineCheckoutController.php:88
* @route '/calls/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/calls/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::create
* @see app/Http/Controllers/SecureLineCheckoutController.php:88
* @route '/calls/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::create
* @see app/Http/Controllers/SecureLineCheckoutController.php:88
* @route '/calls/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::create
* @see app/Http/Controllers/SecureLineCheckoutController.php:88
* @route '/calls/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::store
* @see app/Http/Controllers/SecureLineCheckoutController.php:110
* @route '/calls'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/calls',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::store
* @see app/Http/Controllers/SecureLineCheckoutController.php:110
* @route '/calls'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SecureLineCheckoutController::store
* @see app/Http/Controllers/SecureLineCheckoutController.php:110
* @route '/calls'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CallPageController::join
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
export const join = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(args, options),
    method: 'get',
})

join.definition = {
    methods: ["get","head"],
    url: '/calls/{callSession}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CallPageController::join
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
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
* @see \App\Http\Controllers\CallPageController::join
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
join.get = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CallPageController::join
* @see app/Http/Controllers/CallPageController.php:16
* @route '/calls/{callSession}'
*/
join.head = (args: { callSession: string | number | { id: string | number } } | [callSession: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: join.url(args, options),
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

const calls = {
    index: Object.assign(index, index),
    buy: Object.assign(buy, buy),
    checkout: Object.assign(checkout, checkout),
    awaitCredit: Object.assign(awaitCredit, awaitCredit),
    creditStatus: Object.assign(creditStatus, creditStatus),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    join: Object.assign(join, join),
    room: Object.assign(room, room),
}

export default calls