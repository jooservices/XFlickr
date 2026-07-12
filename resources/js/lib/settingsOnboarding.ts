export const SETTINGS_ONBOARDING_DISMISSED_KEY = 'xflickr.settings.onboarding.dismissed';

export function isSettingsOnboardingDismissed(): boolean {
    try {
        return localStorage.getItem(SETTINGS_ONBOARDING_DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
}

export function dismissSettingsOnboarding(): void {
    try {
        localStorage.setItem(SETTINGS_ONBOARDING_DISMISSED_KEY, '1');
    } catch {
        // ignore quota / private mode
    }
}
