import ConfigController from './ConfigController'
import SecretController from './SecretController'
import FileUploadController from './FileUploadController'
import WebhookController from './WebhookController'
import PipePairingController from './PipePairingController'
import PipeController from './PipeController'
import PipeSignalController from './PipeSignalController'
import CallSignalController from './CallSignalController'

const Api = {
    ConfigController: Object.assign(ConfigController, ConfigController),
    SecretController: Object.assign(SecretController, SecretController),
    FileUploadController: Object.assign(FileUploadController, FileUploadController),
    WebhookController: Object.assign(WebhookController, WebhookController),
    PipePairingController: Object.assign(PipePairingController, PipePairingController),
    PipeController: Object.assign(PipeController, PipeController),
    PipeSignalController: Object.assign(PipeSignalController, PipeSignalController),
    CallSignalController: Object.assign(CallSignalController, CallSignalController),
}

export default Api