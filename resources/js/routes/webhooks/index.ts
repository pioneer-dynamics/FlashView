import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/webhooks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MarkdownDocumentController::index
* @see app/Http/Controllers/MarkdownDocumentController.php:183
* @route '/webhooks'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const webhooks = {
    index: Object.assign(index, index),
}

export default webhooks