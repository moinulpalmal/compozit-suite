import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
export const download = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/merchandising/documents/{documentUpload}/files/{documentFile}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
download.url = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                    documentFile: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                                documentFile: typeof args.documentFile === 'object'
                ? args.documentFile.id
                : args.documentFile,
                }

    return download.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace('{documentFile}', parsedArgs.documentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
download.get = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
download.head = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
    const downloadForm = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
        downloadForm.get = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::download
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:38
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/download'
 */
        downloadForm.head = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
export const preview = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/merchandising/documents/{documentUpload}/files/{documentFile}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
preview.url = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                    documentFile: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                                documentFile: typeof args.documentFile === 'object'
                ? args.documentFile.id
                : args.documentFile,
                }

    return preview.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace('{documentFile}', parsedArgs.documentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
preview.get = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
preview.head = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
    const previewForm = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
        previewForm.get = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::preview
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:62
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}/preview'
 */
        previewForm.head = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    preview.form = previewForm
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::update
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:84
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
export const update = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/merchandising/documents/{documentUpload}/files/{documentFile}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::update
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:84
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
update.url = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                    documentFile: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                                documentFile: typeof args.documentFile === 'object'
                ? args.documentFile.id
                : args.documentFile,
                }

    return update.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace('{documentFile}', parsedArgs.documentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::update
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:84
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
update.post = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::update
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:84
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
    const updateForm = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::update
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:84
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
        updateForm.post = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, options),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:110
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
export const destroy = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/merchandising/documents/{documentUpload}/files/{documentFile}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:110
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
destroy.url = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    documentUpload: args[0],
                    documentFile: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentUpload: typeof args.documentUpload === 'object'
                ? args.documentUpload.id
                : args.documentUpload,
                                documentFile: typeof args.documentFile === 'object'
                ? args.documentFile.id
                : args.documentFile,
                }

    return destroy.definition.url
            .replace('{documentUpload}', parsedArgs.documentUpload.toString())
            .replace('{documentFile}', parsedArgs.documentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:110
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
destroy.delete = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:110
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
    const destroyForm = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Merchandising\DocumentFileController::destroy
 * @see app/Http/Controllers/Merchandising/DocumentFileController.php:110
 * @route '/merchandising/documents/{documentUpload}/files/{documentFile}'
 */
        destroyForm.delete = (args: { documentUpload: number | { id: number }, documentFile: number | { id: number } } | [documentUpload: number | { id: number }, documentFile: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const DocumentFileController = { download, preview, update, destroy }

export default DocumentFileController