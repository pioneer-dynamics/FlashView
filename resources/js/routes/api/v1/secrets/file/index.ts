import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\SecretController::downloaded
* @see app/Http/Controllers/Api/SecretController.php:170
* @route '/api/v1/secrets/{secret}/file/downloaded'
*/
export const downloaded = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: downloaded.url(args, options),
    method: 'post',
})

downloaded.definition = {
    methods: ["post"],
    url: '/api/v1/secrets/{secret}/file/downloaded',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\SecretController::downloaded
* @see app/Http/Controllers/Api/SecretController.php:170
* @route '/api/v1/secrets/{secret}/file/downloaded'
*/
downloaded.url = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { secret: args }
    }

    if (Array.isArray(args)) {
        args = {
            secret: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        secret: args.secret,
    }

    return downloaded.definition.url
            .replace('{secret}', parsedArgs.secret.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SecretController::downloaded
* @see app/Http/Controllers/Api/SecretController.php:170
* @route '/api/v1/secrets/{secret}/file/downloaded'
*/
downloaded.post = (args: { secret: string | number } | [secret: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: downloaded.url(args, options),
    method: 'post',
})

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

const file = {
    downloaded: Object.assign(downloaded, downloaded),
    prepare: Object.assign(prepare, prepare),
    upload: Object.assign(upload, upload),
}

export default file