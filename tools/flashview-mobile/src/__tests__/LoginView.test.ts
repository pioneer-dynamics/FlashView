import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import LoginView from '@/views/LoginView.vue'

const mockPreferences: Record<string, string> = {}

vi.mock('@capacitor/preferences', () => ({
    Preferences: {
        get: vi.fn(({ key }: { key: string }) =>
            Promise.resolve({ value: mockPreferences[key] ?? null }),
        ),
        set: vi.fn(({ key, value }: { key: string; value: string }) => {
            mockPreferences[key] = value
            return Promise.resolve()
        }),
    },
}))

vi.mock('@/composables/useAuth', () => ({
    useAuth: () => ({ login: vi.fn() }),
}))

describe('LoginView — server URL editor', () => {
    beforeEach(() => {
        Object.keys(mockPreferences).forEach((k) => delete mockPreferences[k])
    })

    it('displays the default server URL on mount', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        expect(wrapper.text()).toContain('flashview.link')
    })

    it('displays a stored server URL on mount', async () => {
        mockPreferences['flashview_server_url'] = 'https://my.server.com'
        const wrapper = mount(LoginView)
        await flushPromises()
        expect(wrapper.text()).toContain('my.server.com')
    })

    it('shows the inline editor when "Change" is clicked', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        await wrapper.find('button[type="button"]').trigger('click')
        expect(wrapper.find('input[type="url"]').exists()).toBe(true)
    })

    it('saves a valid URL and closes the editor', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        await wrapper.find('button[type="button"]').trigger('click')

        const input = wrapper.find('input[type="url"]')
        await input.setValue('https://custom.example.com/')

        const buttons = wrapper.findAll('button[type="button"]')
        const saveBtn = buttons.find((b) => b.text() === 'Save')
        await saveBtn!.trigger('click')
        await flushPromises()

        expect(wrapper.find('input[type="url"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('custom.example.com')
        expect(mockPreferences['flashview_server_url']).toBe('https://custom.example.com')
    })

    it('strips the trailing slash when saving', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        await wrapper.find('button[type="button"]').trigger('click')
        await wrapper.find('input[type="url"]').setValue('https://self.hosted.example/flashview/')

        const saveBtn = wrapper.findAll('button[type="button"]').find((b) => b.text() === 'Save')
        await saveBtn!.trigger('click')
        await flushPromises()

        expect(mockPreferences['flashview_server_url']).toBe('https://self.hosted.example/flashview')
    })

    it('shows a validation error for an invalid URL', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        await wrapper.find('button[type="button"]').trigger('click')
        await wrapper.find('input[type="url"]').setValue('not-a-url')

        const saveBtn = wrapper.findAll('button[type="button"]').find((b) => b.text() === 'Save')
        await saveBtn!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('valid URL')
        expect(wrapper.find('input[type="url"]').exists()).toBe(true)
    })

    it('cancels editing without saving', async () => {
        mockPreferences['flashview_server_url'] = 'https://original.server.com'
        const wrapper = mount(LoginView)
        await flushPromises()
        await wrapper.find('button[type="button"]').trigger('click')
        await wrapper.find('input[type="url"]').setValue('https://changed.server.com')

        const cancelBtn = wrapper.findAll('button[type="button"]').find((b) => b.text() === 'Cancel')
        await cancelBtn!.trigger('click')

        expect(wrapper.find('input[type="url"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('original.server.com')
        expect(mockPreferences['flashview_server_url']).toBe('https://original.server.com')
    })
})

describe('LoginView — sign-in button', () => {
    it('renders the Sign in with FlashView button', async () => {
        const wrapper = mount(LoginView)
        await flushPromises()
        const signInBtn = wrapper.findAll('button').find((b) => b.text().includes('Sign in'))
        expect(signInBtn).toBeDefined()
    })
})
