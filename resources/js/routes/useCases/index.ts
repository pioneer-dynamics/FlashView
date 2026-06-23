import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/use-cases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:173
* @route '/use-cases'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const useCases = {
    index: Object.assign(index, index),
}

export default useCases