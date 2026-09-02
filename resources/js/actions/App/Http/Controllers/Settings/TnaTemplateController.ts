import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/master-data/tna-templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::index
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:45
 * @route '/settings/master-data/tna-templates'
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
* @see \App\Http\Controllers\Settings\TnaTemplateController::store
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:112
 * @route '/settings/master-data/tna-templates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/master-data/tna-templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::store
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:112
 * @route '/settings/master-data/tna-templates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::store
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:112
 * @route '/settings/master-data/tna-templates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::store
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:112
 * @route '/settings/master-data/tna-templates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::store
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:112
 * @route '/settings/master-data/tna-templates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
export const update = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/settings/master-data/tna-templates/{tna_template}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
update.url = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tna_template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { tna_template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    tna_template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        tna_template: typeof args.tna_template === 'object'
                ? args.tna_template.id
                : args.tna_template,
                }

    return update.definition.url
            .replace('{tna_template}', parsedArgs.tna_template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
update.put = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
update.patch = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
    const updateForm = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
        updateForm.put = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::update
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:124
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
        updateForm.patch = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Settings\TnaTemplateController::destroy
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:141
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
export const destroy = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/master-data/tna-templates/{tna_template}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::destroy
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:141
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
destroy.url = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tna_template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { tna_template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    tna_template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        tna_template: typeof args.tna_template === 'object'
                ? args.tna_template.id
                : args.tna_template,
                }

    return destroy.definition.url
            .replace('{tna_template}', parsedArgs.tna_template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TnaTemplateController::destroy
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:141
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
destroy.delete = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::destroy
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:141
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
    const destroyForm = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TnaTemplateController::destroy
 * @see app/Http/Controllers/Settings/TnaTemplateController.php:141
 * @route '/settings/master-data/tna-templates/{tna_template}'
 */
        destroyForm.delete = (args: { tna_template: number | { id: number } } | [tna_template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const TnaTemplateController = { index, store, update, destroy }

export default TnaTemplateController