import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\FileUploadController::prepare
* @see app/Http/Controllers/Api/FileUploadController.php:19
* @route '/api/v1/secrets/file/prepare'
*/
export const prepare = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepare.url(options),
    method: 'post',
})

prepare.definition = {
    methods: ["post"],
    url: '/api/v1/secrets/file/prepare',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\FileUploadController::prepare
* @see app/Http/Controllers/Api/FileUploadController.php:19
* @route '/api/v1/secrets/file/prepare'
*/
prepare.url = (options?: RouteQueryOptions) => {
    return prepare.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileUploadController::prepare
* @see app/Http/Controllers/Api/FileUploadController.php:19
* @route '/api/v1/secrets/file/prepare'
*/
prepare.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepare.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\FileUploadController::upload
* @see app/Http/Controllers/Api/FileUploadController.php:55
* @route '/api/v1/secrets/file/upload/{token}'
*/
export const upload = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '/api/v1/secrets/file/upload/{token}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\FileUploadController::upload
* @see app/Http/Controllers/Api/FileUploadController.php:55
* @route '/api/v1/secrets/file/upload/{token}'
*/
upload.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return upload.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileUploadController::upload
* @see app/Http/Controllers/Api/FileUploadController.php:55
* @route '/api/v1/secrets/file/upload/{token}'
*/
upload.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

const FileUploadController = { prepare, upload }

export default FileUploadController