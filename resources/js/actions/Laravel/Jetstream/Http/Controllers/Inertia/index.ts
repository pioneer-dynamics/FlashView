import UserProfileController from './UserProfileController'
import OtherBrowserSessionsController from './OtherBrowserSessionsController'
import ProfilePhotoController from './ProfilePhotoController'
import CurrentUserController from './CurrentUserController'
import ApiTokenController from './ApiTokenController'

const Inertia = {
    UserProfileController: Object.assign(UserProfileController, UserProfileController),
    OtherBrowserSessionsController: Object.assign(OtherBrowserSessionsController, OtherBrowserSessionsController),
    ProfilePhotoController: Object.assign(ProfilePhotoController, ProfilePhotoController),
    CurrentUserController: Object.assign(CurrentUserController, CurrentUserController),
    ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Inertia