import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PosterImage from './PosterImage.vue';

const url = 'https://example.test/batman.jpg';

describe('PosterImage', () => {
    it('shows the poster when there is an address', () => {
        const wrapper = mount(PosterImage, { props: { src: url, alt: 'Batman' } });

        expect(wrapper.find('img').attributes('src')).toBe(url);
        expect(wrapper.find('img').attributes('alt')).toBe('Batman');
        expect(wrapper.find('[data-testid="poster-placeholder"]').exists()).toBe(false);
    });

    it('shows a placeholder when there is no poster', () => {
        const wrapper = mount(PosterImage, { props: { src: null, alt: 'Batman' } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[data-testid="poster-placeholder"]').exists()).toBe(true);
    });

    it('falls back to the placeholder when the image fails to load', async () => {
        const wrapper = mount(PosterImage, { props: { src: url, alt: 'Batman' } });

        await wrapper.find('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[data-testid="poster-placeholder"]').exists()).toBe(true);
    });

    it('tries again when it gets a different poster address', async () => {
        const wrapper = mount(PosterImage, { props: { src: url, alt: 'Batman' } });
        await wrapper.find('img').trigger('error');

        await wrapper.setProps({ src: 'https://example.test/other.jpg' });

        expect(wrapper.find('img').exists()).toBe(true);
    });
});
