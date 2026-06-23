import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PaymentConfirmingController::show
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/payment/confirming',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PaymentConfirmingController::show
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PaymentConfirmingController::show
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PaymentConfirmingController::show
* @see app/Http/Controllers/PaymentConfirmingController.php:12
* @route '/payment/confirming'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

const PaymentConfirmingController = { show }

export default PaymentConfirmingController