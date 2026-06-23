import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::index
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:17
* @route '/admin/secure-line-products'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/secure-line-products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::index
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:17
* @route '/admin/secure-line-products'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::index
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:17
* @route '/admin/secure-line-products'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::index
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:17
* @route '/admin/secure-line-products'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::create
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:24
* @route '/admin/secure-line-products/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/secure-line-products/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::create
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:24
* @route '/admin/secure-line-products/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::create
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:24
* @route '/admin/secure-line-products/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::create
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:24
* @route '/admin/secure-line-products/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::store
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:32
* @route '/admin/secure-line-products'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/secure-line-products',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::store
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:32
* @route '/admin/secure-line-products'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::store
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:32
* @route '/admin/secure-line-products'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::edit
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:56
* @route '/admin/secure-line-products/{secure_line_product}/edit'
*/
export const edit = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/secure-line-products/{secure_line_product}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::edit
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:56
* @route '/admin/secure-line-products/{secure_line_product}/edit'
*/
edit.url = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secure_line_product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { secure_line_product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            secure_line_product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secure_line_product: typeof args.secure_line_product === 'object'
        ? args.secure_line_product.id
        : args.secure_line_product,
    }

    return edit.definition.url
            .replace('{secure_line_product}', parsedArgs.secure_line_product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::edit
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:56
* @route '/admin/secure-line-products/{secure_line_product}/edit'
*/
edit.get = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::edit
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:56
* @route '/admin/secure-line-products/{secure_line_product}/edit'
*/
edit.head = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::update
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:64
* @route '/admin/secure-line-products/{secure_line_product}'
*/
export const update = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/secure-line-products/{secure_line_product}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::update
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:64
* @route '/admin/secure-line-products/{secure_line_product}'
*/
update.url = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secure_line_product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { secure_line_product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            secure_line_product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secure_line_product: typeof args.secure_line_product === 'object'
        ? args.secure_line_product.id
        : args.secure_line_product,
    }

    return update.definition.url
            .replace('{secure_line_product}', parsedArgs.secure_line_product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::update
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:64
* @route '/admin/secure-line-products/{secure_line_product}'
*/
update.put = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::update
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:64
* @route '/admin/secure-line-products/{secure_line_product}'
*/
update.patch = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::destroy
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:89
* @route '/admin/secure-line-products/{secure_line_product}'
*/
export const destroy = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/secure-line-products/{secure_line_product}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::destroy
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:89
* @route '/admin/secure-line-products/{secure_line_product}'
*/
destroy.url = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secure_line_product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { secure_line_product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            secure_line_product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secure_line_product: typeof args.secure_line_product === 'object'
        ? args.secure_line_product.id
        : args.secure_line_product,
    }

    return destroy.definition.url
            .replace('{secure_line_product}', parsedArgs.secure_line_product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminSecureLineProductController::destroy
* @see app/Http/Controllers/Admin/AdminSecureLineProductController.php:89
* @route '/admin/secure-line-products/{secure_line_product}'
*/
destroy.delete = (args: { secure_line_product: string | number | { id: string | number } } | [secure_line_product: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const AdminSecureLineProductController = { index, create, store, edit, update, destroy }

export default AdminSecureLineProductController