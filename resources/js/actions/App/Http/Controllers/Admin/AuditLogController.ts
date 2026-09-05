import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
export const history = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(options),
    method: 'get',
})

history.definition = {
    methods: ["get","head"],
    url: '/admin/audit-logs/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
history.url = (options?: RouteQueryOptions) => {
    return history.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
history.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
history.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: history.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
    const historyForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: history.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
        historyForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: history.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\AuditLogController::history
 * @see app/Http/Controllers/Admin/AuditLogController.php:82
 * @route '/admin/audit-logs/history'
 */
        historyForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: history.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    history.form = historyForm
/**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/audit-logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\AuditLogController::index
 * @see app/Http/Controllers/Admin/AuditLogController.php:39
 * @route '/admin/audit-logs'
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
const AuditLogController = { history, index }

export default AuditLogController