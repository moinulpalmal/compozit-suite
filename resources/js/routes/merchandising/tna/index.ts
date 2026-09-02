import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/merchandising/tna',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\TnaController::index
 * @see app/Http/Controllers/Merchandising/TnaController.php:38
 * @route '/merchandising/tna'
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
const tna = {
    index: Object.assign(index, index),
}

export default tna