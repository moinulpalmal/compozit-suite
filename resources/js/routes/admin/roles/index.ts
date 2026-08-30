import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/roles',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::index
 * @see app/Http/Controllers/Admin/RoleController.php:26
 * @route '/admin/roles'
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
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
export const create = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});

create.definition = {
    methods: ['get', 'head'],
    url: '/admin/roles/create',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
const createForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::create
 * @see app/Http/Controllers/Admin/RoleController.php:57
 * @route '/admin/roles/create'
 */
createForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

create.form = createForm;
/**
 * @see \App\Http\Controllers\Admin\RoleController::store
 * @see app/Http/Controllers/Admin/RoleController.php:67
 * @route '/admin/roles'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/roles',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::store
 * @see app/Http/Controllers/Admin/RoleController.php:67
 * @route '/admin/roles'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::store
 * @see app/Http/Controllers/Admin/RoleController.php:67
 * @route '/admin/roles'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::store
 * @see app/Http/Controllers/Admin/RoleController.php:67
 * @route '/admin/roles'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::store
 * @see app/Http/Controllers/Admin/RoleController.php:67
 * @route '/admin/roles'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
export const edit = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
});

edit.definition = {
    methods: ['get', 'head'],
    url: '/admin/roles/{role}/edit',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
edit.url = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { role: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { role: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            role: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        role: typeof args.role === 'object' ? args.role.id : args.role,
    };

    return (
        edit.definition.url
            .replace('{role}', parsedArgs.role.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
edit.get = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
edit.head = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
const editForm = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
editForm.get = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::edit
 * @see app/Http/Controllers/Admin/RoleController.php:82
 * @route '/admin/roles/{role}/edit'
 */
editForm.head = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

edit.form = editForm;
/**
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
export const update = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

update.definition = {
    methods: ['put', 'patch'],
    url: '/admin/roles/{role}',
} satisfies RouteDefinition<['put', 'patch']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
update.url = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { role: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { role: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            role: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        role: typeof args.role === 'object' ? args.role.id : args.role,
    };

    return (
        update.definition.url
            .replace('{role}', parsedArgs.role.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
update.put = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});
/**
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
update.patch = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
const updateForm = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
updateForm.put = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\RoleController::update
 * @see app/Http/Controllers/Admin/RoleController.php:98
 * @route '/admin/roles/{role}'
 */
updateForm.patch = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\RoleController::destroy
 * @see app/Http/Controllers/Admin/RoleController.php:116
 * @route '/admin/roles/{role}'
 */
export const destroy = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/admin/roles/{role}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\Admin\RoleController::destroy
 * @see app/Http/Controllers/Admin/RoleController.php:116
 * @route '/admin/roles/{role}'
 */
destroy.url = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { role: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { role: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            role: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        role: typeof args.role === 'object' ? args.role.id : args.role,
    };

    return (
        destroy.definition.url
            .replace('{role}', parsedArgs.role.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RoleController::destroy
 * @see app/Http/Controllers/Admin/RoleController.php:116
 * @route '/admin/roles/{role}'
 */
destroy.delete = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\Admin\RoleController::destroy
 * @see app/Http/Controllers/Admin/RoleController.php:116
 * @route '/admin/roles/{role}'
 */
const destroyForm = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\RoleController::destroy
 * @see app/Http/Controllers/Admin/RoleController.php:116
 * @route '/admin/roles/{role}'
 */
destroyForm.delete = (
    args:
        | { role: number | { id: number } }
        | [role: number | { id: number }]
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
const roles = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
};

export default roles;
