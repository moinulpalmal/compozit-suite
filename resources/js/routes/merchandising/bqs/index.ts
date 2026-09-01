import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import importMethod from './import'
/**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/merchandising/bqs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\BqsController::index
 * @see app/Http/Controllers/Merchandising/BqsController.php:45
 * @route '/merchandising/bqs'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
export const show = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/merchandising/bqs/{bqsSheet}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
show.url = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { bqsSheet: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { bqsSheet: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    bqsSheet: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        bqsSheet: typeof args.bqsSheet === 'object'
                ? args.bqsSheet.id
                : args.bqsSheet,
                }

    return show.definition.url
            .replace('{bqsSheet}', parsedArgs.bqsSheet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
show.get = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
show.head = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
    const showForm = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
        showForm.get = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\BqsController::show
 * @see app/Http/Controllers/Merchandising/BqsController.php:111
 * @route '/merchandising/bqs/{bqsSheet}'
 */
        showForm.head = (args: { bqsSheet: number | { id: number } } | [bqsSheet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const bqs = {
    index: Object.assign(index, index),
import: Object.assign(importMethod, importMethod),
show: Object.assign(show, show),
}

export default bqs