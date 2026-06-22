export interface Passkey {
    id: number
    name: string
    created_at: string
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
}

export interface ApiToken {
    id: number
    name: string
    abilities: string[]
    last_used_at: string | null
    created_at: string
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
        path: string
        per_page: number
        to: number | null
        total: number
    }
}
