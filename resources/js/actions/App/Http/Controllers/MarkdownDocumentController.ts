import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::terms
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
export const terms = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: terms.url(options),
    method: 'get',
})

terms.definition = {
    methods: ["get","head"],
    url: '/terms-of-service',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::terms
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
terms.url = (options?: RouteQueryOptions) => {
    return terms.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::terms
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
terms.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: terms.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::terms
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
terms.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: terms.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::privacy
* @see app/Http/Controllers/MarkdownDocumentController.php:163
* @route '/privacy-policy'
*/
export const privacy = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: privacy.url(options),
    method: 'get',
})

privacy.definition = {
    methods: ["get","head"],
    url: '/privacy-policy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::privacy
* @see app/Http/Controllers/MarkdownDocumentController.php:163
* @route '/privacy-policy'
*/
privacy.url = (options?: RouteQueryOptions) => {
    return privacy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::privacy
* @see app/Http/Controllers/MarkdownDocumentController.php:163
* @route '/privacy-policy'
*/
privacy.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: privacy.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::privacy
* @see app/Http/Controllers/MarkdownDocumentController.php:163
* @route '/privacy-policy'
*/
privacy.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: privacy.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::license
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
export const license = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: license.url(options),
    method: 'get',
})

license.definition = {
    methods: ["get","head"],
    url: '/license',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::license
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
license.url = (options?: RouteQueryOptions) => {
    return license.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::license
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
license.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: license.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::license
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
license.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: license.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::security
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
export const security = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(options),
    method: 'get',
})

security.definition = {
    methods: ["get","head"],
    url: '/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::security
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
security.url = (options?: RouteQueryOptions) => {
    return security.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::security
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
security.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::security
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
security.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: security.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::faq
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
export const faq = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: faq.url(options),
    method: 'get',
})

faq.definition = {
    methods: ["get","head"],
    url: '/faq',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::faq
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
faq.url = (options?: RouteQueryOptions) => {
    return faq.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::faq
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
faq.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: faq.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::faq
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
faq.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: faq.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::about
* @see app/Http/Controllers/MarkdownDocumentController.php:168
* @route '/about'
*/
export const about = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
})

about.definition = {
    methods: ["get","head"],
    url: '/about',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::about
* @see app/Http/Controllers/MarkdownDocumentController.php:168
* @route '/about'
*/
about.url = (options?: RouteQueryOptions) => {
    return about.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::about
* @see app/Http/Controllers/MarkdownDocumentController.php:168
* @route '/about'
*/
about.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::about
* @see app/Http/Controllers/MarkdownDocumentController.php:168
* @route '/about'
*/
about.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: about.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::useCases
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
export const useCases = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: useCases.url(options),
    method: 'get',
})

useCases.definition = {
    methods: ["get","head"],
    url: '/use-cases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::useCases
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
useCases.url = (options?: RouteQueryOptions) => {
    return useCases.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::useCases
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
useCases.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: useCases.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::useCases
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
useCases.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: useCases.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::cli
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
export const cli = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cli.url(options),
    method: 'get',
})

cli.definition = {
    methods: ["get","head"],
    url: '/cli',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::cli
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
cli.url = (options?: RouteQueryOptions) => {
    return cli.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::cli
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
cli.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cli.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::cli
* @see app/Http/Controllers/MarkdownDocumentController.php:178
* @route '/cli'
*/
cli.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: cli.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::webhooks
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
export const webhooks = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: webhooks.url(options),
    method: 'get',
})

webhooks.definition = {
    methods: ["get","head"],
    url: '/webhooks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::webhooks
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
webhooks.url = (options?: RouteQueryOptions) => {
    return webhooks.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::webhooks
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
webhooks.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: webhooks.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::webhooks
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
webhooks.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: webhooks.url(options),
    method: 'head',
})

const MarkdownDocumentController = { terms, privacy, license, security, faq, about, useCases, cli, webhooks }

export default MarkdownDocumentController