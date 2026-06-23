import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PlanController::index
* @see app/Http/Controllers/PlanController.php:22
* @route '/plans'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlanController::index
* @see app/Http/Controllers/PlanController.php:22
* @route '/plans'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanController::index
* @see app/Http/Controllers/PlanController.php:22
* @route '/plans'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlanController::index
* @see app/Http/Controllers/PlanController.php:22
* @route '/plans'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PlanController::subscribe
* @see app/Http/Controllers/PlanController.php:43
* @route '/plans/{plan}/{period}'
*/
export const subscribe = (args: { plan: string | number | { id: string | number }, period: string | number } | [plan: string | number | { id: string | number }, period: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: subscribe.url(args, options),
    method: 'get',
})

subscribe.definition = {
    methods: ["get","head"],
    url: '/plans/{plan}/{period}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlanController::subscribe
* @see app/Http/Controllers/PlanController.php:43
* @route '/plans/{plan}/{period}'
*/
subscribe.url = (args: { plan: string | number | { id: string | number }, period: string | number } | [plan: string | number | { id: string | number }, period: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            plan: args[0],
            period: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
        period: args.period,
    }

    return subscribe.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace('{period}', parsedArgs.period.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanController::subscribe
* @see app/Http/Controllers/PlanController.php:43
* @route '/plans/{plan}/{period}'
*/
subscribe.get = (args: { plan: string | number | { id: string | number }, period: string | number } | [plan: string | number | { id: string | number }, period: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: subscribe.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlanController::subscribe
* @see app/Http/Controllers/PlanController.php:43
* @route '/plans/{plan}/{period}'
*/
subscribe.head = (args: { plan: string | number | { id: string | number }, period: string | number } | [plan: string | number | { id: string | number }, period: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: subscribe.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PlanController::unsubscribe
* @see app/Http/Controllers/PlanController.php:29
* @route '/plans/cancel'
*/
export const unsubscribe = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unsubscribe.url(options),
    method: 'post',
})

unsubscribe.definition = {
    methods: ["post"],
    url: '/plans/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlanController::unsubscribe
* @see app/Http/Controllers/PlanController.php:29
* @route '/plans/cancel'
*/
unsubscribe.url = (options?: RouteQueryOptions) => {
    return unsubscribe.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanController::unsubscribe
* @see app/Http/Controllers/PlanController.php:29
* @route '/plans/cancel'
*/
unsubscribe.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unsubscribe.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PlanController::resume
* @see app/Http/Controllers/PlanController.php:38
* @route '/plans/resume'
*/
export const resume = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/plans/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlanController::resume
* @see app/Http/Controllers/PlanController.php:38
* @route '/plans/resume'
*/
resume.url = (options?: RouteQueryOptions) => {
    return resume.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanController::resume
* @see app/Http/Controllers/PlanController.php:38
* @route '/plans/resume'
*/
resume.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(options),
    method: 'post',
})

const plans = {
    index: Object.assign(index, index),
    subscribe: Object.assign(subscribe, subscribe),
    unsubscribe: Object.assign(unsubscribe, unsubscribe),
    resume: Object.assign(resume, resume),
}

export default plans