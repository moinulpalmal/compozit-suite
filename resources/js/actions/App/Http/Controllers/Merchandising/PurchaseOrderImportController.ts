import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/merchandising/purchase-orders/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::resolve
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:84
 * @route '/merchandising/purchase-orders/imports/{poImport}/resolve'
 */
export const resolve = (args: { poImport: number | { id: number } } | [poImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

resolve.definition = {
    methods: ["post"],
    url: '/merchandising/purchase-orders/imports/{poImport}/resolve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::resolve
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:84
 * @route '/merchandising/purchase-orders/imports/{poImport}/resolve'
 */
resolve.url = (args: { poImport: number | { id: number } } | [poImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { poImport: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { poImport: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    poImport: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        poImport: typeof args.poImport === 'object'
                ? args.poImport.id
                : args.poImport,
                }

    return resolve.definition.url
            .replace('{poImport}', parsedArgs.poImport.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::resolve
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:84
 * @route '/merchandising/purchase-orders/imports/{poImport}/resolve'
 */
resolve.post = (args: { poImport: number | { id: number } } | [poImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::resolve
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:84
 * @route '/merchandising/purchase-orders/imports/{poImport}/resolve'
 */
    const resolveForm = (args: { poImport: number | { id: number } } | [poImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::resolve
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:84
 * @route '/merchandising/purchase-orders/imports/{poImport}/resolve'
 */
        resolveForm.post = (args: { poImport: number | { id: number } } | [poImport: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, options),
            method: 'post',
        })
    
    resolve.form = resolveForm
const PurchaseOrderImportController = { store, resolve }

export default PurchaseOrderImportController