import ProfileController from './ProfileController'
import SecurityController from './SecurityController'
import AppearanceController from './AppearanceController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
SecurityController: Object.assign(SecurityController, SecurityController),
AppearanceController: Object.assign(AppearanceController, AppearanceController),
}

export default Settings