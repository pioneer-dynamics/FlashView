import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:153
* @route '/security'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

const security = {
    show: Object.assign(show, show),
}

export default security