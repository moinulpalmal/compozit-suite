import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import files from './files'
/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/merchandising/documents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::index
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:58
 * @route '/merchandising/documents'
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
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::store
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:136
 * @route '/merchandising/documents'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/merchandising/documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::store
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:136
 * @route '/merchandising/documents'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::store
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:136
 * @route '/merchandising/documents'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::store
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:136
 * @route '/merchandising/documents'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::store
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:136
 * @route '/merchandising/documents'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
export const show = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/merchandising/documents/{documentUpload}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
show.url = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { documentUpload: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { documentUpload: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                }

    return show.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
show.get = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
show.head = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
    const showForm = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
        showForm.get = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::show
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:100
 * @route '/merchandising/documents/{documentUpload}'
 */
        showForm.head = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:162
 * @route '/merchandising/documents/{documentUpload}'
 */
export const destroy = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/merchandising/documents/{documentUpload}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:162
 * @route '/merchandising/documents/{documentUpload}'
 */
destroy.url = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { documentUpload: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { documentUpload: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                }

    return destroy.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:162
 * @route '/merchandising/documents/{documentUpload}'
 */
destroy.delete = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:162
 * @route '/merchandising/documents/{documentUpload}'
 */
    const destroyForm = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentUploadController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentUploadController.php:162
 * @route '/merchandising/documents/{documentUpload}'
 */
        destroyForm.delete = (args: { documentUpload: number | { id: number } } | [documentUpload: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const documents = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
files: Object.assign(files, files),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
}

export default documents