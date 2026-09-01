import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/master-data/notification-colors',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::index
 * @see app/Http/Controllers/Settings/NotificationColorController.php:33
 * @route '/settings/master-data/notification-colors'
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
* @see \App\Http\Controllers\Settings\NotificationColorController::store
 * @see app/Http/Controllers/Settings/NotificationColorController.php:63
 * @route '/settings/master-data/notification-colors'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/master-data/notification-colors',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::store
 * @see app/Http/Controllers/Settings/NotificationColorController.php:63
 * @route '/settings/master-data/notification-colors'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::store
 * @see app/Http/Controllers/Settings/NotificationColorController.php:63
 * @route '/settings/master-data/notification-colors'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Settings\NotificationColorController::store
 * @see app/Http/Controllers/Settings/NotificationColorController.php:63
 * @route '/settings/master-data/notification-colors'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::store
 * @see app/Http/Controllers/Settings/NotificationColorController.php:63
 * @route '/settings/master-data/notification-colors'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
export const update = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/settings/master-data/notification-colors/{notification_color}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
update.url = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification_color: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { notification_color: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    notification_color: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification_color: typeof args.notification_color === 'object'
                ? args.notification_color.id
                : args.notification_color,
                }

    return update.definition.url
            .replace('{notification_color}', parsedArgs.notification_color.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
update.put = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
update.patch = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
    const updateForm = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
        updateForm.put = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::update
 * @see app/Http/Controllers/Settings/NotificationColorController.php:75
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
        updateForm.patch = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Settings\NotificationColorController::destroy
 * @see app/Http/Controllers/Settings/NotificationColorController.php:91
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
export const destroy = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/master-data/notification-colors/{notification_color}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::destroy
 * @see app/Http/Controllers/Settings/NotificationColorController.php:91
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
destroy.url = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification_color: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { notification_color: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    notification_color: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification_color: typeof args.notification_color === 'object'
                ? args.notification_color.id
                : args.notification_color,
                }

    return destroy.definition.url
            .replace('{notification_color}', parsedArgs.notification_color.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationColorController::destroy
 * @see app/Http/Controllers/Settings/NotificationColorController.php:91
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
destroy.delete = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Settings\NotificationColorController::destroy
 * @see app/Http/Controllers/Settings/NotificationColorController.php:91
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
    const destroyForm = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\NotificationColorController::destroy
 * @see app/Http/Controllers/Settings/NotificationColorController.php:91
 * @route '/settings/master-data/notification-colors/{notification_color}'
 */
        destroyForm.delete = (args: { notification_color: number | { id: number } } | [notification_color: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const notificationColors = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default notificationColors