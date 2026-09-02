import HomeController from './HomeController'
import Settings from './Settings'
import Admin from './Admin'
import Merchandising from './Merchandising'
const Controllers = {
    HomeController: Object.assign(HomeController, HomeController),
Settings: Object.assign(Settings, Settings),
Admin: Object.assign(Admin, Admin),
Merchandising: Object.assign(Merchandising, Merchandising),
}

export default Controllers