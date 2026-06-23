import notificationSettings from './notification-settings'
import settings from './settings'
import notificationPreferences from './notification-preferences'
import webhookSettings from './webhook-settings'
import senderIdentity from './sender-identity'

const user = {
    notificationSettings: Object.assign(notificationSettings, notificationSettings),
    settings: Object.assign(settings, settings),
    notificationPreferences: Object.assign(notificationPreferences, notificationPreferences),
    webhookSettings: Object.assign(webhookSettings, webhookSettings),
    senderIdentity: Object.assign(senderIdentity, senderIdentity),
}

export default user