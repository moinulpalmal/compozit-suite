import RoleController from './RoleController';
import PermissionController from './PermissionController';
const Admin = {
    RoleController: Object.assign(RoleController, RoleController),
    PermissionController: Object.assign(
        PermissionController,
        PermissionController,
    ),
};

export default Admin;
