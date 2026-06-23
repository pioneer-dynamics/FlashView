import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AdminCouponController::index
* @see app/Http/Controllers/Admin/AdminCouponController.php:23
* @route '/admin/coupons'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/coupons',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::index
* @see app/Http/Controllers/Admin/AdminCouponController.php:23
* @route '/admin/coupons'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::index
* @see app/Http/Controllers/Admin/AdminCouponController.php:23
* @route '/admin/coupons'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::index
* @see app/Http/Controllers/Admin/AdminCouponController.php:23
* @route '/admin/coupons'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::create
* @see app/Http/Controllers/Admin/AdminCouponController.php:30
* @route '/admin/coupons/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/coupons/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::create
* @see app/Http/Controllers/Admin/AdminCouponController.php:30
* @route '/admin/coupons/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::create
* @see app/Http/Controllers/Admin/AdminCouponController.php:30
* @route '/admin/coupons/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::create
* @see app/Http/Controllers/Admin/AdminCouponController.php:30
* @route '/admin/coupons/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::store
* @see app/Http/Controllers/Admin/AdminCouponController.php:35
* @route '/admin/coupons'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/coupons',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::store
* @see app/Http/Controllers/Admin/AdminCouponController.php:35
* @route '/admin/coupons'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::store
* @see app/Http/Controllers/Admin/AdminCouponController.php:35
* @route '/admin/coupons'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::show
* @see app/Http/Controllers/Admin/AdminCouponController.php:62
* @route '/admin/coupons/{coupon}'
*/
export const show = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/admin/coupons/{coupon}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::show
* @see app/Http/Controllers/Admin/AdminCouponController.php:62
* @route '/admin/coupons/{coupon}'
*/
show.url = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { coupon: args }
    }

    if (Array.isArray(args)) {
        args = {
            coupon: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        coupon: args.coupon,
    }

    return show.definition.url
            .replace('{coupon}', parsedArgs.coupon.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::show
* @see app/Http/Controllers/Admin/AdminCouponController.php:62
* @route '/admin/coupons/{coupon}'
*/
show.get = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::show
* @see app/Http/Controllers/Admin/AdminCouponController.php:62
* @route '/admin/coupons/{coupon}'
*/
show.head = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::destroy
* @see app/Http/Controllers/Admin/AdminCouponController.php:82
* @route '/admin/coupons/{coupon}'
*/
export const destroy = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/coupons/{coupon}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::destroy
* @see app/Http/Controllers/Admin/AdminCouponController.php:82
* @route '/admin/coupons/{coupon}'
*/
destroy.url = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { coupon: args }
    }

    if (Array.isArray(args)) {
        args = {
            coupon: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        coupon: args.coupon,
    }

    return destroy.definition.url
            .replace('{coupon}', parsedArgs.coupon.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::destroy
* @see app/Http/Controllers/Admin/AdminCouponController.php:82
* @route '/admin/coupons/{coupon}'
*/
destroy.delete = (args: { coupon: string | number } | [coupon: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::togglePromoCode
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
export const togglePromoCode = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: togglePromoCode.url(args, options),
    method: 'patch',
})

togglePromoCode.definition = {
    methods: ["patch"],
    url: '/admin/coupons/{coupon}/promotion-codes/{promoCode}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::togglePromoCode
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
togglePromoCode.url = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions) => {
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

    return togglePromoCode.definition.url
            .replace('{coupon}', parsedArgs.coupon.toString())
            .replace('{promoCode}', parsedArgs.promoCode.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminCouponController::togglePromoCode
* @see app/Http/Controllers/Admin/AdminCouponController.php:99
* @route '/admin/coupons/{coupon}/promotion-codes/{promoCode}'
*/
togglePromoCode.patch = (args: { coupon: string | number, promoCode: string | number } | [coupon: string | number, promoCode: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: togglePromoCode.url(args, options),
    method: 'patch',
})

const AdminCouponController = { index, create, store, show, destroy, togglePromoCode }

export default AdminCouponController