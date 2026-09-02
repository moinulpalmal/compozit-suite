import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::store
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:42
 * @route '/merchandising/bqs/import'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/merchandising/bqs/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::store
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:42
 * @route '/merchandising/bqs/import'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::store
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:42
 * @route '/merchandising/bqs/import'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\BqsImportController::store
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:42
 * @route '/merchandising/bqs/import'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\BqsImportController::store
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:42
 * @route '/merchandising/bqs/import'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::resolve
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:82
 * @route '/merchandising/bqs/imports/{bqsImport}/resolve'
 */
export const resolve = (args: { bqsImport: number | { id: number } } | [bqsImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

resolve.definition = {
    methods: ["post"],
    url: '/merchandising/bqs/imports/{bqsImport}/resolve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::resolve
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:82
 * @route '/merchandising/bqs/imports/{bqsImport}/resolve'
 */
resolve.url = (args: { bqsImport: number | { id: number } } | [bqsImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { bqsImport: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { bqsImport: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    bqsImport: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        bqsImport: typeof args.bqsImport === 'object'
                ? args.bqsImport.id
                : args.bqsImport,
                }

    return resolve.definition.url
            .replace('{bqsImport}', parsedArgs.bqsImport.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\BqsImportController::resolve
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:82
 * @route '/merchandising/bqs/imports/{bqsImport}/resolve'
 */
resolve.post = (args: { bqsImport: number | { id: number } } | [bqsImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\BqsImportController::resolve
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:82
 * @route '/merchandising/bqs/imports/{bqsImport}/resolve'
 */
    const resolveForm = (args: { bqsImport: number | { id: number } } | [bqsImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\BqsImportController::resolve
 * @see app/Http/Controllers/Merchandising/BqsImportController.php:82
 * @route '/merchandising/bqs/imports/{bqsImport}/resolve'
 */
        resolveForm.post = (args: { bqsImport: number | { id: number } } | [bqsImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, options),
            method: 'post',
        })
    
    resolve.form = resolveForm
const BqsImportController = { store, resolve }

export default BqsImportController