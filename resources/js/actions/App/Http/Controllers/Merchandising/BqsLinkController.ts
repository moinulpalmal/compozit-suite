import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Merchandising\BqsLinkController::update
 * @see app/Http/Controllers/Merchandising/BqsLinkController.php:35
 * @route '/merchandising/purchase-orders/{purchaseOrder}/bqs-link'
 */
export const update = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/merchandising/purchase-orders/{purchaseOrder}/bqs-link',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Merchandising\BqsLinkController::update
 * @see app/Http/Controllers/Merchandising/BqsLinkController.php:35
 * @route '/merchandising/purchase-orders/{purchaseOrder}/bqs-link'
 */
update.url = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchaseOrder: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { purchaseOrder: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    purchaseOrder: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        purchaseOrder: typeof args.purchaseOrder === 'object'
                ? args.purchaseOrder.id
                : args.purchaseOrder,
                }

    return update.definition.url
            .replace('{purchaseOrder}', parsedArgs.purchaseOrder.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\BqsLinkController::update
 * @see app/Http/Controllers/Merchandising/BqsLinkController.php:35
 * @route '/merchandising/purchase-orders/{purchaseOrder}/bqs-link'
 */
update.put = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Merchandising\BqsLinkController::update
 * @see app/Http/Controllers/Merchandising/BqsLinkController.php:35
 * @route '/merchandising/purchase-orders/{purchaseOrder}/bqs-link'
 */
    const updateForm = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\BqsLinkController::update
 * @see app/Http/Controllers/Merchandising/BqsLinkController.php:35
 * @route '/merchandising/purchase-orders/{purchaseOrder}/bqs-link'
 */
        updateForm.put = (args: { purchaseOrder: number | { id: number } } | [purchaseOrder: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const BqsLinkController = { update }

export default BqsLinkController