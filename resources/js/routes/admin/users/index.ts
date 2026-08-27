import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
export const availability = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: availability.url(options),
    method: 'get',
});

availability.definition = {
    methods: ['get', 'head'],
    url: '/admin/users/availability',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
availability.url = (options?: RouteQueryOptions) => {
    return availability.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
availability.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: availability.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
availability.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: availability.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
const availabilityForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: availability.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
availabilityForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: availability.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\UserController::availability
 * @see app/Http/Controllers/Admin/UserController.php:184
 * @route '/admin/users/availability'
 */
availabilityForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: availability.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

availability.form = availabilityForm;
/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/users',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\UserController::index
 * @see app/Http/Controllers/Admin/UserController.php:35
 * @route '/admin/users'
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
 * @see \App\Http\Controllers\Admin\UserController::store
 * @see app/Http/Controllers/Admin/UserController.php:67
 * @route '/admin/users'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/users',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::store
 * @see app/Http/Controllers/Admin/UserController.php:67
 * @route '/admin/users'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\UserController::store
 * @see app/Http/Controllers/Admin/UserController.php:67
 * @route '/admin/users'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::store
 * @see app/Http/Controllers/Admin/UserController.php:67
 * @route '/admin/users'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::store
 * @see app/Http/Controllers/Admin/UserController.php:67
 * @route '/admin/users'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
export const update = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

update.definition = {
    methods: ['put', 'patch'],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<['put', 'patch']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
update.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        update.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
update.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});
/**
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
update.patch = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
const updateForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
updateForm.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\UserController::update
 * @see app/Http/Controllers/Admin/UserController.php:83
 * @route '/admin/users/{user}'
 */
updateForm.patch = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\UserController::destroy
 * @see app/Http/Controllers/Admin/UserController.php:101
 * @route '/admin/users/{user}'
 */
export const destroy = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::destroy
 * @see app/Http/Controllers/Admin/UserController.php:101
 * @route '/admin/users/{user}'
 */
destroy.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        destroy.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::destroy
 * @see app/Http/Controllers/Admin/UserController.php:101
 * @route '/admin/users/{user}'
 */
destroy.delete = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::destroy
 * @see app/Http/Controllers/Admin/UserController.php:101
 * @route '/admin/users/{user}'
 */
const destroyForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
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
 * @see \App\Http\Controllers\Admin\UserController::destroy
 * @see app/Http/Controllers/Admin/UserController.php:101
 * @route '/admin/users/{user}'
 */
destroyForm.delete = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
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
/**
 * @see \App\Http\Controllers\Admin\UserController::restore
 * @see app/Http/Controllers/Admin/UserController.php:119
 * @route '/admin/users/{user}/restore'
 */
export const restore = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
});

restore.definition = {
    methods: ['put'],
    url: '/admin/users/{user}/restore',
} satisfies RouteDefinition<['put']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::restore
 * @see app/Http/Controllers/Admin/UserController.php:119
 * @route '/admin/users/{user}/restore'
 */
restore.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        restore.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::restore
 * @see app/Http/Controllers/Admin/UserController.php:119
 * @route '/admin/users/{user}/restore'
 */
restore.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: restore.url(args, options),
    method: 'put',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::restore
 * @see app/Http/Controllers/Admin/UserController.php:119
 * @route '/admin/users/{user}/restore'
 */
const restoreForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: restore.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::restore
 * @see app/Http/Controllers/Admin/UserController.php:119
 * @route '/admin/users/{user}/restore'
 */
restoreForm.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: restore.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

restore.form = restoreForm;
/**
 * @see \App\Http\Controllers\Admin\UserController::forceDelete
 * @see app/Http/Controllers/Admin/UserController.php:131
 * @route '/admin/users/{user}/force'
 */
export const forceDelete = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
});

forceDelete.definition = {
    methods: ['delete'],
    url: '/admin/users/{user}/force',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::forceDelete
 * @see app/Http/Controllers/Admin/UserController.php:131
 * @route '/admin/users/{user}/force'
 */
forceDelete.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        forceDelete.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::forceDelete
 * @see app/Http/Controllers/Admin/UserController.php:131
 * @route '/admin/users/{user}/force'
 */
forceDelete.delete = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: forceDelete.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::forceDelete
 * @see app/Http/Controllers/Admin/UserController.php:131
 * @route '/admin/users/{user}/force'
 */
const forceDeleteForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: forceDelete.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::forceDelete
 * @see app/Http/Controllers/Admin/UserController.php:131
 * @route '/admin/users/{user}/force'
 */
forceDeleteForm.delete = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: forceDelete.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

forceDelete.form = forceDeleteForm;
/**
 * @see \App\Http\Controllers\Admin\UserController::password
 * @see app/Http/Controllers/Admin/UserController.php:149
 * @route '/admin/users/{user}/password'
 */
export const password = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: password.url(args, options),
    method: 'put',
});

password.definition = {
    methods: ['put'],
    url: '/admin/users/{user}/password',
} satisfies RouteDefinition<['put']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::password
 * @see app/Http/Controllers/Admin/UserController.php:149
 * @route '/admin/users/{user}/password'
 */
password.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        password.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::password
 * @see app/Http/Controllers/Admin/UserController.php:149
 * @route '/admin/users/{user}/password'
 */
password.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: password.url(args, options),
    method: 'put',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::password
 * @see app/Http/Controllers/Admin/UserController.php:149
 * @route '/admin/users/{user}/password'
 */
const passwordForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: password.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::password
 * @see app/Http/Controllers/Admin/UserController.php:149
 * @route '/admin/users/{user}/password'
 */
passwordForm.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: password.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

password.form = passwordForm;
/**
 * @see \App\Http\Controllers\Admin\UserController::roles
 * @see app/Http/Controllers/Admin/UserController.php:161
 * @route '/admin/users/{user}/roles'
 */
export const roles = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: roles.url(args, options),
    method: 'put',
});

roles.definition = {
    methods: ['put'],
    url: '/admin/users/{user}/roles',
} satisfies RouteDefinition<['put']>;

/**
 * @see \App\Http\Controllers\Admin\UserController::roles
 * @see app/Http/Controllers/Admin/UserController.php:161
 * @route '/admin/users/{user}/roles'
 */
roles.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        roles.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\UserController::roles
 * @see app/Http/Controllers/Admin/UserController.php:161
 * @route '/admin/users/{user}/roles'
 */
roles.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: roles.url(args, options),
    method: 'put',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::roles
 * @see app/Http/Controllers/Admin/UserController.php:161
 * @route '/admin/users/{user}/roles'
 */
const rolesForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: roles.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\UserController::roles
 * @see app/Http/Controllers/Admin/UserController.php:161
 * @route '/admin/users/{user}/roles'
 */
rolesForm.put = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: roles.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

roles.form = rolesForm;
const users = {
    availability: Object.assign(availability, availability),
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    restore: Object.assign(restore, restore),
    forceDelete: Object.assign(forceDelete, forceDelete),
    password: Object.assign(password, password),
    roles: Object.assign(roles, roles),
};

export default users;
