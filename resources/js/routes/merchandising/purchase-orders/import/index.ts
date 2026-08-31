import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
export const create = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});

create.definition = {
    methods: ['get', 'head'],
    url: '/merchandising/purchase-orders/import',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
const createForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
 */
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::create
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:33
 * @route '/merchandising/purchase-orders/import'
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
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/merchandising/purchase-orders/import',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Merchandising\PurchaseOrderImportController::store
 * @see app/Http/Controllers/Merchandising/PurchaseOrderImportController.php:45
 * @route '/merchandising/purchase-orders/import'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
const importMethod = {
    create: Object.assign(create, create),
    store: Object.assign(store, store),
};

export default importMethod;
