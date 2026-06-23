import Auth from './Auth'
import MarkdownDocumentController from './MarkdownDocumentController'
import Api from './Api'
import CallSessionController from './CallSessionController'
import FileUploadController from './FileUploadController'
import SecretController from './SecretController'
import PlanController from './PlanController'
import BlogController from './BlogController'
import CliAuthController from './CliAuthController'
import CliDeviceController from './CliDeviceController'
import PaymentConfirmingController from './PaymentConfirmingController'
import NotificationSettingsController from './NotificationSettingsController'
import ConfigurationController from './ConfigurationController'
import NotificationPreferencesController from './NotificationPreferencesController'
import CliInstallationController from './CliInstallationController'
import WebhookSettingsController from './WebhookSettingsController'
import SenderIdentityController from './SenderIdentityController'
import Admin from './Admin'
import CallPageController from './CallPageController'
import SecureLineCheckoutController from './SecureLineCheckoutController'
import LockerController from './LockerController'

const Controllers = {
    Auth: Object.assign(Auth, Auth),
    MarkdownDocumentController: Object.assign(MarkdownDocumentController, MarkdownDocumentController),
    Api: Object.assign(Api, Api),
    CallSessionController: Object.assign(CallSessionController, CallSessionController),
    FileUploadController: Object.assign(FileUploadController, FileUploadController),
    SecretController: Object.assign(SecretController, SecretController),
    PlanController: Object.assign(PlanController, PlanController),
    BlogController: Object.assign(BlogController, BlogController),
    CliAuthController: Object.assign(CliAuthController, CliAuthController),
    CliDeviceController: Object.assign(CliDeviceController, CliDeviceController),
    PaymentConfirmingController: Object.assign(PaymentConfirmingController, PaymentConfirmingController),
    NotificationSettingsController: Object.assign(NotificationSettingsController, NotificationSettingsController),
    ConfigurationController: Object.assign(ConfigurationController, ConfigurationController),
    NotificationPreferencesController: Object.assign(NotificationPreferencesController, NotificationPreferencesController),
    CliInstallationController: Object.assign(CliInstallationController, CliInstallationController),
    WebhookSettingsController: Object.assign(WebhookSettingsController, WebhookSettingsController),
    SenderIdentityController: Object.assign(SenderIdentityController, SenderIdentityController),
    Admin: Object.assign(Admin, Admin),
    CallPageController: Object.assign(CallPageController, CallPageController),
    SecureLineCheckoutController: Object.assign(SecureLineCheckoutController, SecureLineCheckoutController),
    LockerController: Object.assign(LockerController, LockerController),
}

export default Controllers