import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LockerController::prepare
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
export const prepare = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepare.url(options),
    method: 'post',
})

prepare.definition = {
    methods: ["post"],
    url: '/lockers/file/prepare',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LockerController::prepare
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
prepare.url = (options?: RouteQueryOptions) => {
    return prepare.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LockerController::prepare
* @see app/Http/Controllers/LockerController.php:161
* @route '/lockers/file/prepare'
*/
prepare.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepare.url(options),
    method: 'post',
})

const file = {
    prepare: Object.assign(prepare, prepare),
}

export default file