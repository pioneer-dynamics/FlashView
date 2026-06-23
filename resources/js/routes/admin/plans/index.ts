import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AdminPlanController::index
* @see app/Http/Controllers/Admin/AdminPlanController.php:25
* @route '/admin/plans'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::index
* @see app/Http/Controllers/Admin/AdminPlanController.php:25
* @route '/admin/plans'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::index
* @see app/Http/Controllers/Admin/AdminPlanController.php:25
* @route '/admin/plans'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::index
* @see app/Http/Controllers/Admin/AdminPlanController.php:25
* @route '/admin/plans'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::create
* @see app/Http/Controllers/Admin/AdminPlanController.php:32
* @route '/admin/plans/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/plans/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::create
* @see app/Http/Controllers/Admin/AdminPlanController.php:32
* @route '/admin/plans/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::create
* @see app/Http/Controllers/Admin/AdminPlanController.php:32
* @route '/admin/plans/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::create
* @see app/Http/Controllers/Admin/AdminPlanController.php:32
* @route '/admin/plans/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::store
* @see app/Http/Controllers/Admin/AdminPlanController.php:42
* @route '/admin/plans'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/plans',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::store
* @see app/Http/Controllers/Admin/AdminPlanController.php:42
* @route '/admin/plans'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::store
* @see app/Http/Controllers/Admin/AdminPlanController.php:42
* @route '/admin/plans'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::edit
* @see app/Http/Controllers/Admin/AdminPlanController.php:85
* @route '/admin/plans/{plan}/edit'
*/
export const edit = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/plans/{plan}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::edit
* @see app/Http/Controllers/Admin/AdminPlanController.php:85
* @route '/admin/plans/{plan}/edit'
*/
edit.url = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return edit.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::edit
* @see app/Http/Controllers/Admin/AdminPlanController.php:85
* @route '/admin/plans/{plan}/edit'
*/
edit.get = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::edit
* @see app/Http/Controllers/Admin/AdminPlanController.php:85
* @route '/admin/plans/{plan}/edit'
*/
edit.head = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::update
* @see app/Http/Controllers/Admin/AdminPlanController.php:95
* @route '/admin/plans/{plan}'
*/
export const update = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/plans/{plan}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::update
* @see app/Http/Controllers/Admin/AdminPlanController.php:95
* @route '/admin/plans/{plan}'
*/
update.url = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return update.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::update
* @see app/Http/Controllers/Admin/AdminPlanController.php:95
* @route '/admin/plans/{plan}'
*/
update.put = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::update
* @see app/Http/Controllers/Admin/AdminPlanController.php:95
* @route '/admin/plans/{plan}'
*/
update.patch = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::destroy
* @see app/Http/Controllers/Admin/AdminPlanController.php:156
* @route '/admin/plans/{plan}'
*/
export const destroy = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/plans/{plan}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::destroy
* @see app/Http/Controllers/Admin/AdminPlanController.php:156
* @route '/admin/plans/{plan}'
*/
destroy.url = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return destroy.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminPlanController::destroy
* @see app/Http/Controllers/Admin/AdminPlanController.php:156
* @route '/admin/plans/{plan}'
*/
destroy.delete = (args: { plan: string | number | { id: string | number } } | [plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const plans = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default plans