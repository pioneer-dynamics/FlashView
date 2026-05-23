import posthog from 'posthog-js';

let initialised = false;

export function initPostHog() {
    if (initialised) { return; }
    const key = import.meta.env.VITE_POSTHOG_KEY;
    const apiHost = import.meta.env.VITE_POSTHOG_API_HOST;
    const uiHost = import.meta.env.VITE_POSTHOG_UI_HOST;
    if (!key || !uiHost || !apiHost) { return; }
    posthog.init(key, { api_host: apiHost, ui_host: uiHost, person_profiles: 'identified_only' });
    posthog.register({ environment: import.meta.env.VITE_APP_ENV });
    initialised = true;
}

export function identifyUser(userId, properties = {}) {
    if (!initialised) { return; }
    posthog.identify(String(userId), properties);
}

export function resetUser() {
    if (!initialised) { return; }
    posthog.reset();
}

export function captureEvent(event, properties = {}) {
    if (!initialised) { return; }
    posthog.capture(event, properties);
}
