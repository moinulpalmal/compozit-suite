import UserController from './UserController';
import RoleController from './RoleController';
import PermissionController from './PermissionController';
const Admin = {
    UserController: Object.assign(UserController, UserController),
    RoleController: Object.assign(RoleController, RoleController),
    PermissionController: Object.assign(
        PermissionController,
        PermissionController,
    ),
};

export default Admin;
