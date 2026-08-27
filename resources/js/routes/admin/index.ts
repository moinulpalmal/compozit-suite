import users from './users';
import roles from './roles';
import permissions from './permissions';
const admin = {
    users: Object.assign(users, users),
    roles: Object.assign(roles, roles),
    permissions: Object.assign(permissions, permissions),
};

export default admin;
