import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LockerController::index
* @see app/Http/Controllers/LockerController.php:32
* @route '/lockers'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/lockers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::index
* @see app/Http/Controllers/LockerController.php:32
* @route '/lockers'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::index
* @see app/Http/Controllers/LockerController.php:32
* @route '/lockers'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::index
* @see app/Http/Controllers/LockerController.php:32
* @route '/lockers'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::buy
* @see app/Http/Controllers/LockerController.php:37
* @route '/lockers/buy'
*/
export const buy = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buy.url(options),
    method: 'get',
})

buy.definition = {
    methods: ["get","head"],
    url: '/lockers/buy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::buy
* @see app/Http/Controllers/LockerController.php:37
* @route '/lockers/buy'
*/
buy.url = (options?: RouteQueryOptions) => {
    return buy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::buy
* @see app/Http/Controllers/LockerController.php:37
* @route '/lockers/buy'
*/
buy.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buy.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::buy
* @see app/Http/Controllers/LockerController.php:37
* @route '/lockers/buy'
*/
buy.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: buy.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::prepareFile
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
export const prepareFile = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareFile.url(options),
    method: 'post',
})

prepareFile.definition = {
    methods: ["post"],
    url: '/lockers/file/prepare',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::prepareFile
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
prepareFile.url = (options?: RouteQueryOptions) => {
    return prepareFile.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::prepareFile
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
prepareFile.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareFile.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LockerController::checkout
* @see app/Http/Controllers/LockerController.php:51
* @route '/lockers/checkout'
*/
export const checkout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(options),
    method: 'post',
})

checkout.definition = {
    methods: ["post"],
    url: '/lockers/checkout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::checkout
* @see app/Http/Controllers/LockerController.php:51
* @route '/lockers/checkout'
*/
checkout.url = (options?: RouteQueryOptions) => {
    return checkout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::checkout
* @see app/Http/Controllers/LockerController.php:51
* @route '/lockers/checkout'
*/
checkout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LockerController::awaitCredit
* @see app/Http/Controllers/LockerController.php:85
* @route '/lockers/await-credit'
*/
export const awaitCredit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: awaitCredit.url(options),
    method: 'get',
})

awaitCredit.definition = {
    methods: ["get","head"],
    url: '/lockers/await-credit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::awaitCredit
* @see app/Http/Controllers/LockerController.php:85
* @route '/lockers/await-credit'
*/
awaitCredit.url = (options?: RouteQueryOptions) => {
    return awaitCredit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::awaitCredit
* @see app/Http/Controllers/LockerController.php:85
* @route '/lockers/await-credit'
*/
awaitCredit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: awaitCredit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::awaitCredit
* @see app/Http/Controllers/LockerController.php:85
* @route '/lockers/await-credit'
*/
awaitCredit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: awaitCredit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::creditStatus
* @see app/Http/Controllers/LockerController.php:92
* @route '/lockers/credit-status'
*/
export const creditStatus = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: creditStatus.url(options),
    method: 'get',
})

creditStatus.definition = {
    methods: ["get","head"],
    url: '/lockers/credit-status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::creditStatus
* @see app/Http/Controllers/LockerController.php:92
* @route '/lockers/credit-status'
*/
creditStatus.url = (options?: RouteQueryOptions) => {
    return creditStatus.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::creditStatus
* @see app/Http/Controllers/LockerController.php:92
* @route '/lockers/credit-status'
*/
creditStatus.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: creditStatus.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::creditStatus
* @see app/Http/Controllers/LockerController.php:92
* @route '/lockers/credit-status'
*/
creditStatus.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: creditStatus.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::create
* @see app/Http/Controllers/LockerController.php:109
* @route '/lockers/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/lockers/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::create
* @see app/Http/Controllers/LockerController.php:109
* @route '/lockers/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::create
* @see app/Http/Controllers/LockerController.php:109
* @route '/lockers/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::create
* @see app/Http/Controllers/LockerController.php:109
* @route '/lockers/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::open
* @see app/Http/Controllers/LockerController.php:265
* @route '/lockers/open'
*/
export const open = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(options),
    method: 'get',
})

open.definition = {
    methods: ["get","head"],
    url: '/lockers/open',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::open
* @see app/Http/Controllers/LockerController.php:265
* @route '/lockers/open'
*/
open.url = (options?: RouteQueryOptions) => {
    return open.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::open
* @see app/Http/Controllers/LockerController.php:265
* @route '/lockers/open'
*/
open.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::open
* @see app/Http/Controllers/LockerController.php:265
* @route '/lockers/open'
*/
open.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: open.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::renewPage
* @see app/Http/Controllers/LockerController.php:272
* @route '/lockers/renew'
*/
export const renewPage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: renewPage.url(options),
    method: 'get',
})

renewPage.definition = {
    methods: ["get","head"],
    url: '/lockers/renew',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::renewPage
* @see app/Http/Controllers/LockerController.php:272
* @route '/lockers/renew'
*/
renewPage.url = (options?: RouteQueryOptions) => {
    return renewPage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::renewPage
* @see app/Http/Controllers/LockerController.php:272
* @route '/lockers/renew'
*/
renewPage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: renewPage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::renewPage
* @see app/Http/Controllers/LockerController.php:272
* @route '/lockers/renew'
*/
renewPage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: renewPage.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::store
* @see app/Http/Controllers/LockerController.php:125
* @route '/lockers'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/lockers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::store
* @see app/Http/Controllers/LockerController.php:125
* @route '/lockers'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::store
* @see app/Http/Controllers/LockerController.php:125
* @route '/lockers'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LockerController::authInfo
* @see app/Http/Controllers/LockerController.php:187
* @route '/lockers/{accountId}/auth-info'
*/
export const authInfo = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authInfo.url(args, options),
    method: 'get',
})

authInfo.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/auth-info',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::authInfo
* @see app/Http/Controllers/LockerController.php:187
* @route '/lockers/{accountId}/auth-info'
*/
authInfo.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return authInfo.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::authInfo
* @see app/Http/Controllers/LockerController.php:187
* @route '/lockers/{accountId}/auth-info'
*/
authInfo.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authInfo.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::authInfo
* @see app/Http/Controllers/LockerController.php:187
* @route '/lockers/{accountId}/auth-info'
*/
authInfo.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authInfo.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::updateSettings
* @see app/Http/Controllers/LockerController.php:223
* @route '/lockers/{accountId}/settings'
*/
export const updateSettings = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSettings.url(args, options),
    method: 'patch',
})

updateSettings.definition = {
    methods: ["patch"],
    url: '/lockers/{accountId}/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\LockerController::updateSettings
* @see app/Http/Controllers/LockerController.php:223
* @route '/lockers/{accountId}/settings'
*/
updateSettings.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return updateSettings.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::updateSettings
* @see app/Http/Controllers/LockerController.php:223
* @route '/lockers/{accountId}/settings'
*/
updateSettings.patch = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSettings.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\LockerController::show
* @see app/Http/Controllers/LockerController.php:277
* @route '/lockers/{accountId}'
*/
export const show = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::show
* @see app/Http/Controllers/LockerController.php:277
* @route '/lockers/{accountId}'
*/
show.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return show.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::show
* @see app/Http/Controllers/LockerController.php:277
* @route '/lockers/{accountId}'
*/
show.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::show
* @see app/Http/Controllers/LockerController.php:277
* @route '/lockers/{accountId}'
*/
show.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:282
* @route '/lockers/{accountId}/challenge'
*/
export const challenge = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

challenge.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/challenge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:282
* @route '/lockers/{accountId}/challenge'
*/
challenge.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return challenge.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:282
* @route '/lockers/{accountId}/challenge'
*/
challenge.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::challenge
* @see app/Http/Controllers/LockerController.php:282
* @route '/lockers/{accountId}/challenge'
*/
challenge.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: challenge.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::unlock
* @see app/Http/Controllers/LockerController.php:304
* @route '/lockers/{accountId}/unlock'
*/
export const unlock = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unlock.url(args, options),
    method: 'post',
})

unlock.definition = {
    methods: ["post"],
    url: '/lockers/{accountId}/unlock',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::unlock
* @see app/Http/Controllers/LockerController.php:304
* @route '/lockers/{accountId}/unlock'
*/
unlock.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return unlock.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::unlock
* @see app/Http/Controllers/LockerController.php:304
* @route '/lockers/{accountId}/unlock'
*/
unlock.post = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unlock.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LockerController::payload
* @see app/Http/Controllers/LockerController.php:382
* @route '/lockers/{accountId}/payload'
*/
export const payload = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: payload.url(args, options),
    method: 'get',
})

payload.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/payload',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::payload
* @see app/Http/Controllers/LockerController.php:382
* @route '/lockers/{accountId}/payload'
*/
payload.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return payload.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::payload
* @see app/Http/Controllers/LockerController.php:382
* @route '/lockers/{accountId}/payload'
*/
payload.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: payload.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::payload
* @see app/Http/Controllers/LockerController.php:382
* @route '/lockers/{accountId}/payload'
*/
payload.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: payload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::update
* @see app/Http/Controllers/LockerController.php:439
* @route '/lockers/{accountId}'
*/
export const update = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/lockers/{accountId}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\LockerController::update
* @see app/Http/Controllers/LockerController.php:439
* @route '/lockers/{accountId}'
*/
update.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return update.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::update
* @see app/Http/Controllers/LockerController.php:439
* @route '/lockers/{accountId}'
*/
update.put = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\LockerController::destroy
* @see app/Http/Controllers/LockerController.php:519
* @route '/lockers/{accountId}'
*/
export const destroy = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/lockers/{accountId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\LockerController::destroy
* @see app/Http/Controllers/LockerController.php:519
* @route '/lockers/{accountId}'
*/
destroy.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return destroy.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::destroy
* @see app/Http/Controllers/LockerController.php:519
* @route '/lockers/{accountId}'
*/
destroy.delete = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\LockerController::downloadUrl
* @see app/Http/Controllers/LockerController.php:402
* @route '/lockers/{accountId}/download-url'
*/
export const downloadUrl = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadUrl.url(args, options),
    method: 'get',
})

downloadUrl.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/download-url',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::downloadUrl
* @see app/Http/Controllers/LockerController.php:402
* @route '/lockers/{accountId}/download-url'
*/
downloadUrl.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return downloadUrl.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::downloadUrl
* @see app/Http/Controllers/LockerController.php:402
* @route '/lockers/{accountId}/download-url'
*/
downloadUrl.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadUrl.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::downloadUrl
* @see app/Http/Controllers/LockerController.php:402
* @route '/lockers/{accountId}/download-url'
*/
downloadUrl.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: downloadUrl.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::renewChallenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
export const renewChallenge = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: renewChallenge.url(args, options),
    method: 'get',
})

renewChallenge.definition = {
    methods: ["get","head"],
    url: '/lockers/{accountId}/renew',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LockerController::renewChallenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
renewChallenge.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return renewChallenge.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::renewChallenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
renewChallenge.get = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: renewChallenge.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LockerController::renewChallenge
* @see app/Http/Controllers/LockerController.php:563
* @route '/lockers/{accountId}/renew'
*/
renewChallenge.head = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: renewChallenge.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LockerController::renewPurchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
export const renewPurchase = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: renewPurchase.url(args, options),
    method: 'post',
})

renewPurchase.definition = {
    methods: ["post"],
    url: '/lockers/{accountId}/renew',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::renewPurchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
renewPurchase.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return renewPurchase.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::renewPurchase
* @see app/Http/Controllers/LockerController.php:584
* @route '/lockers/{accountId}/renew'
*/
renewPurchase.post = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: renewPurchase.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LockerController::upgradeAuth
* @see app/Http/Controllers/LockerController.php:652
* @route '/lockers/{accountId}/upgrade-auth'
*/
export const upgradeAuth = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upgradeAuth.url(args, options),
    method: 'post',
})

upgradeAuth.definition = {
    methods: ["post"],
    url: '/lockers/{accountId}/upgrade-auth',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::upgradeAuth
* @see app/Http/Controllers/LockerController.php:652
* @route '/lockers/{accountId}/upgrade-auth'
*/
upgradeAuth.url = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { accountId: args }
    }

    if (Array.isArray(args)) {
        args = {
            accountId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        accountId: args.accountId,
    }

    return upgradeAuth.definition.url
            .replace('{accountId}', parsedArgs.accountId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::upgradeAuth
* @see app/Http/Controllers/LockerController.php:652
* @route '/lockers/{accountId}/upgrade-auth'
*/
upgradeAuth.post = (args: { accountId: string | number } | [accountId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upgradeAuth.url(args, options),
    method: 'post',
})

const LockerController = { index, buy, prepareFile, checkout, awaitCredit, creditStatus, create, open, renewPage, store, authInfo, updateSettings, show, challenge, unlock, payload, update, destroy, downloadUrl, renewChallenge, renewPurchase, upgradeAuth }

export default LockerController