import plans from './plans'
import lockerPlans from './locker-plans'
import secureLineProducts from './secure-line-products'
import coupons from './coupons'
import users from './users'

const admin = {
    plans: Object.assign(plans, plans),
    lockerPlans: Object.assign(lockerPlans, lockerPlans),
    secureLineProducts: Object.assign(secureLineProducts, secureLineProducts),
    coupons: Object.assign(coupons, coupons),
    users: Object.assign(users, users),
}

export default admin