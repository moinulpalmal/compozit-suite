import HomeController from './HomeController'
import Settings from './Settings'
import Admin from './Admin'
const Controllers = {
    HomeController: Object.assign(HomeController, HomeController),
Settings: Object.assign(Settings, Settings),
Admin: Object.assign(Admin, Admin),
}

export default Controllers