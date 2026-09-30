import type { Directive } from 'vue';

/**
 * v-reveal: fades and lifts an element into place the first time it scrolls
 * into view. Optional value is a stagger delay in milliseconds. Elements stay
 * visible (no transform, no transition) when the user prefers reduced motion
 * or IntersectionObserver is unavailable.
 */
const observed = new WeakMap<Element, IntersectionObserver>();

function prefersReducedMotion(): boolean {
    return (
        typeof window === 'undefined' ||
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (prefersReducedMotion()) {
            return;
        }

        el.dataset.reveal = 'hidden';
        el.style.setProperty('--reveal-delay', `${binding.value ?? 0}ms`);

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        el.dataset.reveal = 'shown';
                        observer.disconnect();
                        observed.delete(el);
                    }
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(el);
        observed.set(el, observer);
    },
    unmounted(el) {
        observed.get(el)?.disconnect();
        observed.delete(el);
    },
};

/**
 * Runs a callback once when the element first becomes visible (immediately
 * under reduced motion). Returns a cleanup function.
 */
export function onFirstVisible(el: Element, callback: () => void): () => void {
    if (prefersReducedMotion()) {
        callback();

        return () => {};
    }

    const observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                callback();
                observer.disconnect();
            }
        },
        { threshold: 0.4 },
    );

    observer.observe(el);

    return () => observer.disconnect();
}

export { prefersReducedMotion };
