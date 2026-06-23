import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AdminCouponController::toggle
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
export const toggle = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/admin/coupons/{coupon}/promotion-codes/{promoCode}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::toggle
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
toggle.url = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            coupon: args[0],
            promoCode: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        coupon: args.coupon,
        promoCode: args.promoCode,
    }

    return toggle.definition.url
            .replace('{coupon}', parsedArgs.coupon.toString())
            .replace('{promoCode}', parsedArgs.promoCode.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::toggle
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
toggle.patch = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

const promoCodes = {
    toggle: Object.assign(toggle, toggle),
}

export default promoCodes