export interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

export interface Pagination<T> {
    data: T[]
    links: {
        first: string | null
        last: string | null
        prev: string | null
        next: string | null
    }
    meta: {
        current_page: number
        from: number | null
        last_page: number
        links: PaginationLink[]
        path: string
        per_page: number
        to: number | null
        total: number
    }
}

export interface Passkey {
    id: number
    name: string
    created_at: string
    public_key: string
}

export interface PlanSettings {
    expiry: { expiry_minutes: number } | null
    messages: { message_length: number } | null
    file_upload: {
        max_file_size_mb: number
        allowed_mime_types: string[]
    } | null
}

export interface Plan {
    id: number
    name: string
    price_monthly: number
    price_yearly: number
    stripe_price_id_monthly: string
    stripe_price_id_yearly: string
    features: string[]
    settings: PlanSettings
}

export interface Subscription {
    stripe_status: string
    name: string
    ends_at: string | null
}

export interface Team {
    id: number
    name: string
    personal_team: boolean
}

export interface User {
    id: number
    name: string
    email: string
    email_verified_at: string | null
    profile_photo_url: string | null
    two_factor_enabled: boolean
    is_admin: boolean
    subscription: Subscription | null
    plan: Plan | null
    current_team: Team | null
    current_team_id: number | null
    all_teams: Team[]
    passkeys: Passkey[]
}

export interface Secret {
    hash_id: string
    created_at: string
    expires_at: string | null
    retrieved_at: string | null
    masked_recipient_email: string | null
    is_file: boolean
    file: string | null
    file_mime_type: string | null
    file_size: number | null
}

export interface Locker {
    id: number
    name: string
    slug: string
    created_at: string
    secrets_count: number
}

export interface BlogPost {
    slug: string
    title: string
    excerpt: string
    published_at: string
    date_formatted: string
    tags: string[]
    author: string
    body: string
}

export interface ApiToken {
    id: number
    name: string
    abilities: string[]
    last_used_at: string | null
    last_used_ago: string | null
    created_at: string
    type: 'api' | 'cli'
}

export interface SecureLineProduct {
    id: number
    name: string
    duration_minutes: number
    max_participants: number
    amount_cents: number
    stripe_price_id: string | null
    is_active: boolean
}

export interface StripeCoupon {
    id: string
    name: string | null
    percent_off: number | null
    amount_off: number | null
    currency: string | null
    duration: string
    duration_in_months: number | null
    max_redemptions: number | null
    times_redeemed: number | null
    redeem_by: number | null
    valid: boolean
    applies_to: { products: string[] } | null
}

export interface PromoCodeRestrictions {
    minimum_amount: number | null
    minimum_amount_currency: string | null
}

export interface StripePromoCode {
    id: string
    code: string
    active: boolean
    times_redeemed: number | null
    max_redemptions: number | null
    restrictions: PromoCodeRestrictions | null
    created: number | null
}

export interface PlanFeatureEntry {
    type: string
    order: number
    config: Record<string, string>
}

export interface AdminPlan {
    id: number
    name: string
    price_per_month: number
    price_per_year: number
    is_free_plan: boolean
    stripe_product_id: string | null
    stripe_monthly_price_id: string | null
    stripe_yearly_price_id: string | null
    start_date: string | null
    end_date: string | null
    features: Record<string, PlanFeatureEntry> | null
}

export interface ConfigSchemaField {
    key: string
    label: string
    type: string
    default: unknown
    min?: number
    options?: { label: string; value: string }[]
}

export interface AvailableFeature {
    key: string
    label: string
    description: string
    canBeLimit: boolean
    configSchema: ConfigSchemaField[]
}

export interface IncludedFeature {
    key: string
    type: 'feature' | 'limit'
    config: Record<string, string>
}

export interface AdminUser {
    id: number
    name: string
    email: string
    plan_name: string | null
    subscription_status: string | null
    joined_at: string
    is_suspended: boolean
}

export interface LockerPlan {
    id: number
    tier: string
    years: number
    file_size_mb: number | null
    amount_cents: number
    stripe_price_id: string | null
    is_active: boolean
}

export interface PlanFeature {
    type: 'feature' | 'limit' | 'missing'
    label: string
}

export interface PricingPlan {
    id: number
    name: string
    price_per_month: number
    price_per_year: number
    stripe_monthly_price_id: string
    stripe_yearly_price_id: string
    is_available: boolean
    start_date: string | null
    end_date: string | null
    features: PlanFeature[]
}

export interface PricingPlanCollection {
    data: PricingPlan[]
}

export interface CallSession {
    bridge_number: string
    starts_at: string
    ends_at: string
    is_active: boolean
}

export interface RoomSession {
    bridge_number: string
    ends_at: string
}

export interface PeerEntry {
    pc: RTCPeerConnection
    connectionFailed: boolean
}

export interface BrowserSession {
    agent: {
        is_desktop: boolean
        platform: string | null
        browser: string | null
    }
    ip_address: string
    is_current_device: boolean
    last_active: string
}

export interface LockerCredentials {
    account_id: string
    passphrase: string
    expires_at: string
    keyFileNames: string[]
    authMode: string
}

export interface FileMeta {
    name: string
    type: string
    size: number
}

export interface SenderIdentityDetail {
    type: 'email' | 'domain'
    company_name: string
    domain: string | null
    include_by_default: boolean
    is_verified: boolean
    verification_token: string | null
    has_active_retry: boolean
}
