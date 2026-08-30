import ProfileController from './ProfileController';
import SecurityController from './SecurityController';
import AppearanceController from './AppearanceController';
import NotificationColorController from './NotificationColorController';
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    SecurityController: Object.assign(SecurityController, SecurityController),
    AppearanceController: Object.assign(
        AppearanceController,
        AppearanceController,
    ),
    NotificationColorController: Object.assign(
        NotificationColorController,
        NotificationColorController,
    ),
};

export default Settings;
