import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\CliInstallationController::destroy
* @see app/Http/Controllers/CliInstallationController.php:10
* @route '/user/cli-installations/{token}'
*/
export const destroy = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/user/cli-installations/{token}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\CliInstallationController::destroy
* @see app/Http/Controllers/CliInstallationController.php:10
* @route '/user/cli-installations/{token}'
*/
destroy.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CliInstallationController::destroy
* @see app/Http/Controllers/CliInstallationController.php:10
* @route '/user/cli-installations/{token}'
*/
destroy.delete = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const cliInstallations = {
    destroy: Object.assign(destroy, destroy),
}

export default cliInstallations