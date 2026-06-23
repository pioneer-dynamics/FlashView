import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/license',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::show
* @see app/Http/Controllers/MarkdownDocumentController.php:133
* @route '/license'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

const license = {
    show: Object.assign(show, show),
}

export default license