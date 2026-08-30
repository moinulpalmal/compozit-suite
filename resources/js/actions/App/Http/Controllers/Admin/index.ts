import UserController from './UserController';
import DesignationController from './DesignationController';
import BuyerController from './BuyerController';
import RoleController from './RoleController';
import PermissionController from './PermissionController';
const Admin = {
    UserController: Object.assign(UserController, UserController),
    DesignationController: Object.assign(
        DesignationController,
        DesignationController,
    ),
    BuyerController: Object.assign(BuyerController, BuyerController),
    RoleController: Object.assign(RoleController, RoleController),
    PermissionController: Object.assign(
        PermissionController,
        PermissionController,
    ),
};

export default Admin;
