import PurchaseOrderController from './PurchaseOrderController'
import PurchaseOrderImportController from './PurchaseOrderImportController'
import BqsController from './BqsController'
import BqsImportController from './BqsImportController'
const Merchandising = {
    PurchaseOrderController: Object.assign(PurchaseOrderController, PurchaseOrderController),
PurchaseOrderImportController: Object.assign(PurchaseOrderImportController, PurchaseOrderImportController),
BqsController: Object.assign(BqsController, BqsController),
BqsImportController: Object.assign(BqsImportController, BqsImportController),
}

export default Merchandising