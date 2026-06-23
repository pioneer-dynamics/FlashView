import AdminPlanController from './AdminPlanController'
import AdminLockerPlanController from './AdminLockerPlanController'
import AdminSecureLineProductController from './AdminSecureLineProductController'
import AdminCouponController from './AdminCouponController'
import AdminUserController from './AdminUserController'

const Admin = {
    AdminPlanController: Object.assign(AdminPlanController, AdminPlanController),
    AdminLockerPlanController: Object.assign(AdminLockerPlanController, AdminLockerPlanController),
    AdminSecureLineProductController: Object.assign(AdminSecureLineProductController, AdminSecureLineProductController),
    AdminCouponController: Object.assign(AdminCouponController, AdminCouponController),
    AdminUserController: Object.assign(AdminUserController, AdminUserController),
}

export default Admin