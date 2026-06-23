import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/faq',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:138
* @route '/faq'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const faq = {
    index: Object.assign(index, index),
}

export default faq