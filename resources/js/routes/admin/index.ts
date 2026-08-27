import roles from './roles'
import permissions from './permissions'
const admin = {
    roles: Object.assign(roles, roles),
permissions: Object.assign(permissions, permissions),
}

export default admin