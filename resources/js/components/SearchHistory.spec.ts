import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SearchHistory from './SearchHistory.vue';

describe('SearchHistory', () => {
    it('shows nothing when there are no recent searches', () => {
        const wrapper = mount(SearchHistory, { props: { items: [] } });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('shows one chip per recent search, in the given order', () => {
        const wrapper = mount(SearchHistory, { props: { items: ['matrix', 'batman'] } });

        const chips = wrapper.findAll('li button').map((chip) => chip.text());

        expect(chips).toEqual(['matrix', 'batman']);
    });

    it('emits the text of the chip that was clicked', async () => {
        const wrapper = mount(SearchHistory, { props: { items: ['matrix', 'batman'] } });

        await wrapper.findAll('li button')[1]!.trigger('click');

        expect(wrapper.emitted('select')).toEqual([['batman']]);
    });

    it('shows no error line unless there is an error', () => {
        const wrapper = mount(SearchHistory, { props: { items: ['batman'] } });

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('shows the error next to the recent searches', () => {
        const wrapper = mount(SearchHistory, {
            props: { items: ['batman'], error: 'Could not clear the recent searches. Please try again.' },
        });

        expect(wrapper.find('[role="alert"]').text()).toBe('Could not clear the recent searches. Please try again.');
    });

    it('emits clear when Clear is clicked', async () => {
        const wrapper = mount(SearchHistory, { props: { items: ['batman'] } });

        await wrapper.findAll('button').at(-1)!.trigger('click');

        expect(wrapper.emitted('clear')).toHaveLength(1);
        expect(wrapper.emitted('select')).toBeUndefined();
    });
});
