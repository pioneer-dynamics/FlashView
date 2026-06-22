import type { User } from './models'

export interface SenderIdentity {
    type: string
    company_name: string
    domain: string | null
    email: string | null
    include_by_default: boolean
}

export interface ExpiryOption {
    label: string
    value: number
}

export interface SecretsConfig {
    expiry_options: ExpiryOption[]
    expiry_limits: {
        guest: number
        user: number
    }
    message_length: {
        guest: number
        user: number
    }
    prune_after: number
    file_upload: {
        max_file_size_mb: { guest: number; user: number }
        allowed_mime_types?: string[]
    }
    rate_limit: {
        guest: { per_minute: number; per_day: number }
        user: { per_minute: number; per_day: number }
    }
}

export interface SupportConfig {
    email: string | null
    legal: string | null
    security: string | null
    abuse: string | null
}

export interface Auth {
    user: User | null
    hasApiAccess: boolean
    planSupportsEmailNotifications: boolean
    hasWebhookAccess: boolean
    senderIdentity: SenderIdentity | null
    webhook: { webhook_url: string } | null
}

export interface Flash {
    info: string | null
    error: string | null
    alert: string | null
    success: string | null
}

export interface JetstreamFlash {
    bannerStyle?: string
    banner?: string
    token?: string
    webhookSecret?: string
    options?: unknown
    verified?: boolean
    error?: { code?: number; message?: string } | string
    secret?: {
        url?: string
        is_file?: boolean
        message?: string
    }
}

export interface JetstreamConfig {
    canCreateTeams: boolean
    canManageTwoFactorAuthentication: boolean
    canUpdatePassword: boolean
    canUpdateProfileInformation: boolean
    hasAccountDeletionFeatures: boolean
    hasApiFeatures: boolean
    hasEmailVerification: boolean
    hasTeamFeatures: boolean
    hasTermsAndPrivacyPolicyFeature: boolean
    managesProfilePhotos: boolean
    flash: JetstreamFlash
}

export interface PageProps {
    auth: Auth
    errors: Record<string, string>
    flash: Flash
    config: {
        app: { name: string }
        secrets: SecretsConfig
        support: SupportConfig
        access: { enabled: boolean }
    }
    ziggy: unknown
    jetstream: JetstreamConfig
}
