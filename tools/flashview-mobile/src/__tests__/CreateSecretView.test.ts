import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

const mockCreateSecret = vi.fn().mockResolvedValue({ data: { url: 'https://flashview.link/s/abc123' } })

vi.mock('@pioneer-dynamics/flashview-crypto', () => ({
    encryptMessage: vi.fn().mockResolvedValue({ passphrase: 'word-word-word', secret: 'ciphertext' }),
}))

vi.mock('@/composables/useAuth', () => ({
    useAuth: () => ({
        getClient: vi.fn().mockResolvedValue({ createSecret: mockCreateSecret }),
        reAuthenticate: vi.fn(),
    }),
}))

vi.mock('@/composables/useServerConfig', () => ({
    useServerConfig: () => ({
        config: {
            value: {
                maxMessageLength: 10000,
                expiryOptions: [
                    { value: 1440, label: '1 day' },
                    { value: 10080, label: '7 days' },
                ],
            },
        },
        fetchConfig: vi.fn(),
    }),
}))

vi.mock('@/composables/useShareIntent', () => ({
    useShareIntent: () => ({
        sharedText: { value: null },
        clearSharedContent: vi.fn(),
    }),
}))

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/layouts/MobileLayout.vue', () => ({
    default: defineComponent({
        render() {
            return h('div', {}, this.$slots.default?.())
        },
    }),
}))

vi.mock('@/components/ExpiryPicker.vue', () => ({
    default: defineComponent({
        props: ['modelValue', 'options'],
        emits: ['update:modelValue'],
        render() {
            return h('div', { 'data-testid': 'expiry-picker' })
        },
    }),
}))

import CreateSecretView from '@/views/CreateSecretView.vue'

describe('CreateSecretView — custom passphrase toggle', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockCreateSecret.mockResolvedValue({ data: { url: 'https://flashview.link/s/abc123' } })
    })

    it('hides the passphrase field by default (checkbox unchecked)', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        // The passphrase section is hidden until the checkbox is ticked.
        const hideBtn = wrapper.findAll('button').find((b) => b.text() === 'Hide')
        expect(hideBtn).toBeUndefined()
    })

    it('shows the passphrase field as plain text when the checkbox is ticked', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('input[type="checkbox"]').setValue(true)
        // Passphrases default to visible (type=text), not masked.
        const hideBtn = wrapper.findAll('button').find((b) => b.text() === 'Hide')
        expect(hideBtn).toBeDefined()
    })

    it('toggles passphrase masking with the Hide/Show button', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('input[type="checkbox"]').setValue(true)

        // Default: visible (Hide button shown). Click to mask.
        const hideBtn = wrapper.findAll('button').find((b) => b.text() === 'Hide')
        await hideBtn!.trigger('click')
        expect(wrapper.find('input[type="password"]').exists()).toBe(true)

        // Click again to reveal.
        const showBtn = wrapper.findAll('button').find((b) => b.text() === 'Show')
        await showBtn!.trigger('click')
        expect(wrapper.find('input[type="password"]').exists()).toBe(false)
    })
})

describe('CreateSecretView — validation', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockCreateSecret.mockResolvedValue({ data: { url: 'https://flashview.link/s/abc123' } })
    })

    it('does not submit when the message is empty', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Create Secret'))
        await createBtn!.trigger('click')
        await flushPromises()
        expect(mockCreateSecret).not.toHaveBeenCalled()
    })

    it('shows an error when custom passphrase is shorter than 8 characters', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('textarea').setValue('my secret message')
        await wrapper.find('input[type="checkbox"]').setValue(true)
        // Passphrase defaults to visible (type=text); use a data-testid-free selector
        await wrapper.findAll('input[type="text"]').slice(-1)[0].setValue('short')

        const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Create Secret'))
        await createBtn!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('at least 8 characters')
        expect(mockCreateSecret).not.toHaveBeenCalled()
    })

    it('submits successfully with a valid message', async () => {
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('textarea').setValue('a valid secret message')

        const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Create Secret'))
        await createBtn!.trigger('click')
        await flushPromises()

        expect(mockCreateSecret).toHaveBeenCalledOnce()
    })

    it('shows a rate-limit error on 429 response', async () => {
        mockCreateSecret.mockRejectedValueOnce({ status: 429, retryAfter: 30 })
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('textarea').setValue('rate limited message')

        const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Create Secret'))
        await createBtn!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('30 seconds')
    })

    it('shows a network error with a safety message', async () => {
        mockCreateSecret.mockRejectedValueOnce(new TypeError('Failed to fetch'))
        const wrapper = mount(CreateSecretView)
        await flushPromises()
        await wrapper.find('textarea').setValue('network failure message')

        const createBtn = wrapper.findAll('button').find((b) => b.text().includes('Create Secret'))
        await createBtn!.trigger('click')
        await flushPromises()

        expect(wrapper.text()).toContain('NOT sent')
    })
})
