import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
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

const SecureLineCheckoutController = { buy, checkout, awaitCredit, creditStatus, create, store }

export default SecureLineCheckoutController