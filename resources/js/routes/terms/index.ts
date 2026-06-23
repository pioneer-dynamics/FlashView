import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/terms-of-service',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:148
* @route '/terms-of-service'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

const terms = {
    show: Object.assign(show, show),
}

export default terms