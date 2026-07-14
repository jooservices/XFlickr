import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import UserAccountMenu from '@/Components/Layout/UserAccountMenu';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: React.ReactNode;
        [key: string]: unknown;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { post },
    usePage: () => ({ url: '/dashboard' }),
}));

describe('UserAccountMenu', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('opens account menu with profile link and sign out', () => {
        render(
            <UserAccountMenu
                user={{
                    id: 1,
                    name: 'Admin User',
                    email: 'admin@local',
                }}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Account menu' }));

        expect(screen.getByRole('menuitem', { name: 'Profile' }).getAttribute('href')).toBe('/profile');
        expect(screen.getByRole('menuitem', { name: 'Sign out' })).toBeTruthy();
        expect(screen.getByText('Admin User')).toBeTruthy();
        expect(screen.getByText('admin@local')).toBeTruthy();
    });

    it('posts logout when sign out is chosen', () => {
        render(
            <UserAccountMenu
                user={{
                    id: 1,
                    name: 'Admin User',
                    email: 'admin@local',
                }}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Account menu' }));
        fireEvent.click(screen.getByRole('menuitem', { name: 'Sign out' }));

        expect(post).toHaveBeenCalledWith('/logout');
    });
});
