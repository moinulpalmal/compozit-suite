import users from './users'
import designations from './designations'
import buyers from './buyers'
import roles from './roles'
import permissions from './permissions'
const admin = {
    users: Object.assign(users, users),
designations: Object.assign(designations, designations),
buyers: Object.assign(buyers, buyers),
roles: Object.assign(roles, roles),
permissions: Object.assign(permissions, permissions),
}

export default admin