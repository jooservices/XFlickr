import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    dismissSettingsOnboarding,
    isSettingsOnboardingDismissed,
    SETTINGS_ONBOARDING_DISMISSED_KEY,
} from './settingsOnboarding';

describe('settingsOnboarding', () => {
    const store = new Map<string, string>();

    beforeEach(() => {
        store.clear();
        vi.stubGlobal('localStorage', {
            getItem: (key: string) => store.get(key) ?? null,
            setItem: (key: string, value: string) => {
                store.set(key, value);
            },
            removeItem: (key: string) => {
                store.delete(key);
            },
            clear: () => store.clear(),
        });
    });

    it('tracks dismiss state in localStorage', () => {
        expect(isSettingsOnboardingDismissed()).toBe(false);
        dismissSettingsOnboarding();
        expect(store.get(SETTINGS_ONBOARDING_DISMISSED_KEY)).toBe('1');
        expect(isSettingsOnboardingDismissed()).toBe(true);
    });
});
