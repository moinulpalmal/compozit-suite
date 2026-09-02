import PurchaseOrderController from './PurchaseOrderController'
import PurchaseOrderImportController from './PurchaseOrderImportController'
import BqsLinkController from './BqsLinkController'
import BqsController from './BqsController'
import BqsImportController from './BqsImportController'
import TnaController from './TnaController'
import DocumentUploadController from './DocumentUploadController'
import DocumentFileController from './DocumentFileController'
const Merchandising = {
    PurchaseOrderController: Object.assign(PurchaseOrderController, PurchaseOrderController),
PurchaseOrderImportController: Object.assign(PurchaseOrderImportController, PurchaseOrderImportController),
BqsLinkController: Object.assign(BqsLinkController, BqsLinkController),
BqsController: Object.assign(BqsController, BqsController),
BqsImportController: Object.assign(BqsImportController, BqsImportController),
TnaController: Object.assign(TnaController, TnaController),
DocumentUploadController: Object.assign(DocumentUploadController, DocumentUploadController),
DocumentFileController: Object.assign(DocumentFileController, DocumentFileController),
}

export default Merchandising