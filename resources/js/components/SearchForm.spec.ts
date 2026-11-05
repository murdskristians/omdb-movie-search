import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SearchForm from './SearchForm.vue';

describe('SearchForm', () => {
    it('emits the trimmed text when the form is submitted', async () => {
        const wrapper = mount(SearchForm);

        await wrapper.find('input').setValue('  batman ');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('search')).toEqual([['batman']]);
    });

    it('does not emit for blank text', async () => {
        const wrapper = mount(SearchForm);

        await wrapper.find('input').setValue('   ');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('search')).toBeUndefined();
    });

    it('disables the button while disabled is set', async () => {
        const wrapper = mount(SearchForm, { props: { disabled: true } });
        await wrapper.find('input').setValue('batman');

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it('disables the button while the text is blank and enables it once there is text', async () => {
        const wrapper = mount(SearchForm);
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeDefined();

        await wrapper.find('input').setValue('   ');
        expect(button.attributes('disabled')).toBeDefined();

        await wrapper.find('input').setValue('batman');
        expect(button.attributes('disabled')).toBeUndefined();
    });
});
