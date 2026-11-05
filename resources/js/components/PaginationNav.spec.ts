import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaginationNav from './PaginationNav.vue';

function buttons(wrapper: ReturnType<typeof mount>) {
    const [previous, next] = wrapper.findAll('button');

    return { previous: previous!, next: next! };
}

describe('PaginationNav', () => {
    it('shows the current page and the total', () => {
        const wrapper = mount(PaginationNav, { props: { page: 2, lastPage: 5 } });

        expect(wrapper.text()).toContain('Page 2 of 5');
    });

    it('is hidden when there is only one page', () => {
        const wrapper = mount(PaginationNav, { props: { page: 1, lastPage: 1 } });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('disables Previous on the first page', () => {
        const wrapper = mount(PaginationNav, { props: { page: 1, lastPage: 5 } });
        const { previous, next } = buttons(wrapper);

        expect(previous.attributes('disabled')).toBeDefined();
        expect(next.attributes('disabled')).toBeUndefined();
    });

    it('disables Next on the last page', () => {
        const wrapper = mount(PaginationNav, { props: { page: 5, lastPage: 5 } });
        const { previous, next } = buttons(wrapper);

        expect(previous.attributes('disabled')).toBeUndefined();
        expect(next.attributes('disabled')).toBeDefined();
    });

    it('emits the previous and the next page number', async () => {
        const wrapper = mount(PaginationNav, { props: { page: 3, lastPage: 5 } });
        const { previous, next } = buttons(wrapper);

        await previous.trigger('click');
        await next.trigger('click');

        expect(wrapper.emitted('change')).toEqual([[2], [4]]);
    });
});
