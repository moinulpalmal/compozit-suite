import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
export const options = (routeOptions?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
})

options.definition = {
    methods: ["get","head"],
    url: '/admin/buyers/options',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
options.url = (routeOptions?: RouteQueryOptions) => {
    return options.definition.url
 + queryParams(routeOptions)
}

/**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
options.get = (routeOptions?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
options.head = (routeOptions?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: options.url(routeOptions),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
    const optionsForm = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: options.url(
            
                            routeOptions
                   ),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
        optionsForm.get = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: options.url(
                
                                routeOptions
                           ),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\BuyerController::options
 * @see app/Http/Controllers/Admin/BuyerController.php:70
 * @route '/admin/buyers/options'
 */
        optionsForm.head = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: options.url({
                        [routeOptions?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(routeOptions?.query ?? routeOptions?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    options.form = optionsForm
/**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/buyers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\BuyerController::index
 * @see app/Http/Controllers/Admin/BuyerController.php:29
 * @route '/admin/buyers'
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
* @see \App\Http\Controllers\Admin\BuyerController::store
 * @see app/Http/Controllers/Admin/BuyerController.php:84
 * @route '/admin/buyers'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/buyers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\BuyerController::store
 * @see app/Http/Controllers/Admin/BuyerController.php:84
 * @route '/admin/buyers'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BuyerController::store
 * @see app/Http/Controllers/Admin/BuyerController.php:84
 * @route '/admin/buyers'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\BuyerController::store
 * @see app/Http/Controllers/Admin/BuyerController.php:84
 * @route '/admin/buyers'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\BuyerController::store
 * @see app/Http/Controllers/Admin/BuyerController.php:84
 * @route '/admin/buyers'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
export const update = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/buyers/{buyer}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
update.url = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { buyer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { buyer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    buyer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        buyer: typeof args.buyer === 'object'
                ? args.buyer.id
                : args.buyer,
                }

    return update.definition.url
            .replace('{buyer}', parsedArgs.buyer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
update.put = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
update.patch = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
    const updateForm = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
        updateForm.put = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Admin\BuyerController::update
 * @see app/Http/Controllers/Admin/BuyerController.php:96
 * @route '/admin/buyers/{buyer}'
 */
        updateForm.patch = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\BuyerController::destroy
 * @see app/Http/Controllers/Admin/BuyerController.php:114
 * @route '/admin/buyers/{buyer}'
 */
export const destroy = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/buyers/{buyer}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\BuyerController::destroy
 * @see app/Http/Controllers/Admin/BuyerController.php:114
 * @route '/admin/buyers/{buyer}'
 */
destroy.url = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { buyer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { buyer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    buyer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        buyer: typeof args.buyer === 'object'
                ? args.buyer.id
                : args.buyer,
                }

    return destroy.definition.url
            .replace('{buyer}', parsedArgs.buyer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BuyerController::destroy
 * @see app/Http/Controllers/Admin/BuyerController.php:114
 * @route '/admin/buyers/{buyer}'
 */
destroy.delete = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Admin\BuyerController::destroy
 * @see app/Http/Controllers/Admin/BuyerController.php:114
 * @route '/admin/buyers/{buyer}'
 */
    const destroyForm = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\BuyerController::destroy
 * @see app/Http/Controllers/Admin/BuyerController.php:114
 * @route '/admin/buyers/{buyer}'
 */
        destroyForm.delete = (args: { buyer: number | { id: number } } | [buyer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const BuyerController = { options, index, store, update, destroy }

export default BuyerController