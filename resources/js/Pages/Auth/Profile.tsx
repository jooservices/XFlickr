import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/Button';
import Input from '@/Components/Input';
import { PageShell, PageShellCanvas, PageShellIdentity } from '@/Components/layout/page-shell';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';

export default function Profile() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const { data, setData, put, processing, errors, recentlySuccessful, reset } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        current_password: '',
    });

    if (!user) {
        return null;
    }

    const changingSensitive = data.password.trim() !== '' || data.email.trim() !== user.email;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        put('/profile', {
            preserveScroll: true,
            onSuccess: () => reset('password', 'current_password'),
        });
    }

    return (
        <AppLayout>
            <Head title="Profile" />

            <PageShell>
                <PageShellIdentity
                    breadcrumbs={[
                        { label: 'Dashboard', href: '/dashboard' },
                        { label: 'Profile' },
                    ]}
                    title="Profile"
                    subtitle="Update your account details and password."
                />

                <PageShellCanvas className="space-y-6" variant="plain">
                    <form onSubmit={submit} className="mx-auto max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <label htmlFor="name" className="mb-1 block text-sm font-medium text-slate-700">
                                Name
                            </label>
                            <Input
                                id="name"
                                name="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                required
                                autoComplete="name"
                            />
                            {errors.name ? <p className="mt-1 text-sm text-red-600">{errors.name}</p> : null}
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1 block text-sm font-medium text-slate-700">
                                Email
                            </label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                required
                                autoComplete="username"
                            />
                            {errors.email ? <p className="mt-1 text-sm text-red-600">{errors.email}</p> : null}
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1 block text-sm font-medium text-slate-700">
                                New password
                            </label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                autoComplete="new-password"
                                placeholder="Leave blank to keep current password"
                            />
                            {errors.password ? <p className="mt-1 text-sm text-red-600">{errors.password}</p> : null}
                        </div>

                        {changingSensitive ? (
                            <div>
                                <label
                                    htmlFor="current_password"
                                    className="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Current password
                                </label>
                                <Input
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    value={data.current_password}
                                    onChange={(event) => setData('current_password', event.target.value)}
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.current_password ? (
                                    <p className="mt-1 text-sm text-red-600">{errors.current_password}</p>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="flex items-center justify-end gap-3">
                            {recentlySuccessful ? (
                                <p className="text-sm text-cyan-800">Saved.</p>
                            ) : null}
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Saving…' : 'Save changes'}
                            </Button>
                        </div>
                    </form>
                </PageShellCanvas>
            </PageShell>
        </AppLayout>
    );
}
