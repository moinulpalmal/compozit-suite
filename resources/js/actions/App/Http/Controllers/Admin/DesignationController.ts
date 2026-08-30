import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
export const options = (
    routeOptions?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
});

options.definition = {
    methods: ['get', 'head'],
    url: '/admin/designations/options',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
options.url = (routeOptions?: RouteQueryOptions) => {
    return options.definition.url + queryParams(routeOptions);
};

/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
options.get = (routeOptions?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
options.head = (routeOptions?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: options.url(routeOptions),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
const optionsForm = (
    routeOptions?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: options.url(routeOptions),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
optionsForm.get = (
    routeOptions?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: options.url(routeOptions),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::options
 * @see app/Http/Controllers/Admin/DesignationController.php:66
 * @route '/admin/designations/options'
 */
optionsForm.head = (
    routeOptions?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: options.url({
        [routeOptions?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(routeOptions?.query ?? routeOptions?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

options.form = optionsForm;
/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/designations',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::index
 * @see app/Http/Controllers/Admin/DesignationController.php:30
 * @route '/admin/designations'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\Admin\DesignationController::store
 * @see app/Http/Controllers/Admin/DesignationController.php:80
 * @route '/admin/designations'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/designations',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\DesignationController::store
 * @see app/Http/Controllers/Admin/DesignationController.php:80
 * @route '/admin/designations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\DesignationController::store
 * @see app/Http/Controllers/Admin/DesignationController.php:80
 * @route '/admin/designations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::store
 * @see app/Http/Controllers/Admin/DesignationController.php:80
 * @route '/admin/designations'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::store
 * @see app/Http/Controllers/Admin/DesignationController.php:80
 * @route '/admin/designations'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
export const update = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

update.definition = {
    methods: ['put', 'patch'],
    url: '/admin/designations/{designation}',
} satisfies RouteDefinition<['put', 'patch']>;

/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
update.url = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { designation: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { designation: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            designation: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        designation:
            typeof args.designation === 'object'
                ? args.designation.id
                : args.designation,
    };

    return (
        update.definition.url
            .replace('{designation}', parsedArgs.designation.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
update.put = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
update.patch = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
const updateForm = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
updateForm.put = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});
/**
 * @see \App\Http\Controllers\Admin\DesignationController::update
 * @see app/Http/Controllers/Admin/DesignationController.php:92
 * @route '/admin/designations/{designation}'
 */
updateForm.patch = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;
/**
 * @see \App\Http\Controllers\Admin\DesignationController::destroy
 * @see app/Http/Controllers/Admin/DesignationController.php:108
 * @route '/admin/designations/{designation}'
 */
export const destroy = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/admin/designations/{designation}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\Admin\DesignationController::destroy
 * @see app/Http/Controllers/Admin/DesignationController.php:108
 * @route '/admin/designations/{designation}'
 */
destroy.url = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { designation: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { designation: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            designation: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        designation:
            typeof args.designation === 'object'
                ? args.designation.id
                : args.designation,
    };

    return (
        destroy.definition.url
            .replace('{designation}', parsedArgs.designation.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\DesignationController::destroy
 * @see app/Http/Controllers/Admin/DesignationController.php:108
 * @route '/admin/designations/{designation}'
 */
destroy.delete = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::destroy
 * @see app/Http/Controllers/Admin/DesignationController.php:108
 * @route '/admin/designations/{designation}'
 */
const destroyForm = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\DesignationController::destroy
 * @see app/Http/Controllers/Admin/DesignationController.php:108
 * @route '/admin/designations/{designation}'
 */
destroyForm.delete = (
    args:
        | { designation: number | { id: number } }
        | [designation: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

destroy.form = destroyForm;
const DesignationController = { options, index, store, update, destroy };

export default DesignationController;
