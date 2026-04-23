import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ExpiryPicker from '@/components/ExpiryPicker.vue'

const OPTIONS = [
    { value: 60, label: '1 hour' },
    { value: 1440, label: '1 day' },
    { value: 10080, label: '7 days' },
] as const

describe('ExpiryPicker', () => {
    it('renders the selected option label', () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        expect(wrapper.text()).toContain('1 day')
    })

    it('falls back to "1 day" when modelValue is not in options', () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 9999, options: OPTIONS },
        })
        expect(wrapper.text()).toContain('1 day')
    })

    it('does not show the dropdown initially', () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        expect(wrapper.findAll('button').length).toBe(1)
    })

    it('expands to show all options on toggle button click', async () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        await wrapper.find('button').trigger('click')
        const buttons = wrapper.findAll('button')
        expect(buttons.length).toBe(1 + OPTIONS.length)
    })

    it('emits update:modelValue with the selected value', async () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        await wrapper.find('button').trigger('click')
        const optionButtons = wrapper.findAll('button').slice(1)
        await optionButtons[0].trigger('click')
        expect(wrapper.emitted('update:modelValue')).toEqual([[60]])
    })

    it('collapses the dropdown after selecting an option', async () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        await wrapper.find('button').trigger('click')
        const optionButtons = wrapper.findAll('button').slice(1)
        await optionButtons[0].trigger('click')
        expect(wrapper.findAll('button').length).toBe(1)
    })

    it('highlights the currently selected option', async () => {
        const wrapper = mount(ExpiryPicker, {
            props: { modelValue: 1440, options: OPTIONS },
        })
        await wrapper.find('button').trigger('click')
        const optionButtons = wrapper.findAll('button').slice(1)
        const selectedButton = optionButtons.find((b) => b.text().includes('1 day'))
        expect(selectedButton?.classes()).toContain('text-gamboge-300')
    })
})
