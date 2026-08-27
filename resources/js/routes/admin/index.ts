import users from './users'
import designations from './designations'
import roles from './roles'
import permissions from './permissions'
const admin = {
    users: Object.assign(users, users),
designations: Object.assign(designations, designations),
roles: Object.assign(roles, roles),
permissions: Object.assign(permissions, permissions),
}

export default admin