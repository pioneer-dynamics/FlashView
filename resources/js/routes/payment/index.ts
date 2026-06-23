import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PaymentConfirmingController::confirming
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
export const confirming = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirming.url(options),
    method: 'get',
})

confirming.definition = {
    methods: ["get","head"],
    url: '/payment/confirming',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PaymentConfirmingController::confirming
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
confirming.url = (options?: RouteQueryOptions) => {
    return confirming.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PaymentConfirmingController::confirming
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
confirming.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirming.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PaymentConfirmingController::confirming
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
confirming.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: confirming.url(options),
    method: 'head',
})

const payment = {
    confirming: Object.assign(confirming, confirming),
}

export default payment