import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/Button';
import Input from '@/Components/Input';
import GuestLayout from '@/Layouts/GuestLayout';

type ResetPasswordPageProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/reset-password');
    }

    return (
        <GuestLayout>
            <Head title="Reset password" />

            <div className="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-slate-900">Reset password</h1>
                <p className="mt-1 text-sm text-slate-600">Choose a new password for your account.</p>

                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <div>
                        <label htmlFor="email" className="mb-1 block text-sm font-medium text-slate-700">
                            Email
                        </label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            required
                        />
                        {errors.email ? <p className="mt-1 text-sm text-red-600">{errors.email}</p> : null}
                    </div>

                    <div>
                        <label htmlFor="password" className="mb-1 block text-sm font-medium text-slate-700">
                            New password
                        </label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            required
                        />
                        {errors.password ? <p className="mt-1 text-sm text-red-600">{errors.password}</p> : null}
                    </div>

                    <div>
                        <label
                            htmlFor="password_confirmation"
                            className="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Confirm password
                        </label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            required
                        />
                    </div>

                    <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                        {processing ? 'Updating…' : 'Update password'}
                    </Button>
                </form>

                <p className="mt-6 text-sm text-slate-600">
                    <Link href="/login" className="text-cyan-700 hover:underline">
                        Back to sign in
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
