import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::index
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:17
* @route '/admin/locker-plans'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/locker-plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::index
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:17
* @route '/admin/locker-plans'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::index
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:17
* @route '/admin/locker-plans'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::index
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:17
* @route '/admin/locker-plans'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::create
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:24
* @route '/admin/locker-plans/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/locker-plans/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::create
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:24
* @route '/admin/locker-plans/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::create
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:24
* @route '/admin/locker-plans/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::create
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:24
* @route '/admin/locker-plans/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::store
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:32
* @route '/admin/locker-plans'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/locker-plans',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::store
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:32
* @route '/admin/locker-plans'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::store
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:32
* @route '/admin/locker-plans'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::edit
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:55
* @route '/admin/locker-plans/{locker_plan}/edit'
*/
export const edit = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/locker-plans/{locker_plan}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::edit
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:55
* @route '/admin/locker-plans/{locker_plan}/edit'
*/
edit.url = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { locker_plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { locker_plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            locker_plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        locker_plan: typeof args.locker_plan === 'object'
        ? args.locker_plan.id
        : args.locker_plan,
    }

    return edit.definition.url
            .replace('{locker_plan}', parsedArgs.locker_plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::edit
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:55
* @route '/admin/locker-plans/{locker_plan}/edit'
*/
edit.get = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::edit
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:55
* @route '/admin/locker-plans/{locker_plan}/edit'
*/
edit.head = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::update
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:63
* @route '/admin/locker-plans/{locker_plan}'
*/
export const update = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/locker-plans/{locker_plan}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::update
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:63
* @route '/admin/locker-plans/{locker_plan}'
*/
update.url = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { locker_plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { locker_plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            locker_plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        locker_plan: typeof args.locker_plan === 'object'
        ? args.locker_plan.id
        : args.locker_plan,
    }

    return update.definition.url
            .replace('{locker_plan}', parsedArgs.locker_plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::update
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:63
* @route '/admin/locker-plans/{locker_plan}'
*/
update.put = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::update
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:63
* @route '/admin/locker-plans/{locker_plan}'
*/
update.patch = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::destroy
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:86
* @route '/admin/locker-plans/{locker_plan}'
*/
export const destroy = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/locker-plans/{locker_plan}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::destroy
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:86
* @route '/admin/locker-plans/{locker_plan}'
*/
destroy.url = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { locker_plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { locker_plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            locker_plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        locker_plan: typeof args.locker_plan === 'object'
        ? args.locker_plan.id
        : args.locker_plan,
    }

    return destroy.definition.url
            .replace('{locker_plan}', parsedArgs.locker_plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AdminLockerPlanController::destroy
* @see app/Http/Controllers/Admin/AdminLockerPlanController.php:86
* @route '/admin/locker-plans/{locker_plan}'
*/
destroy.delete = (args: { locker_plan: string | number | { id: string | number } } | [locker_plan: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const AdminLockerPlanController = { index, create, store, edit, update, destroy }

export default AdminLockerPlanController