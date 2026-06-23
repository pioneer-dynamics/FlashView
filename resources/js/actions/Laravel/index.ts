import Cashier from './Cashier'
import Fortify from './Fortify'
import Horizon from './Horizon'
import Jetstream from './Jetstream'
import Sanctum from './Sanctum'

const Laravel = {
    Cashier: Object.assign(Cashier, Cashier),
    Fortify: Object.assign(Fortify, Fortify),
    Horizon: Object.assign(Horizon, Horizon),
    Jetstream: Object.assign(Jetstream, Jetstream),
    Sanctum: Object.assign(Sanctum, Sanctum),
}

export default Laravel