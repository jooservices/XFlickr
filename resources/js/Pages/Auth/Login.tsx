import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';

type LoginPageProps = {
    status?: string | null;
};

export default function Login({ status = null }: LoginPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/login');
    }

    return (
        <GuestLayout>
            <Head title="Sign in" />

            <div className="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-slate-900">Sign in</h1>
                <p className="mt-1 text-sm text-slate-600">Sign in to manage your Flickr archive.</p>

                {status ? (
                    <p className="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                        {status}
                    </p>
                ) : null}

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
                            Password
                        </label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            required
                        />
                        {errors.password ? <p className="mt-1 text-sm text-red-600">{errors.password}</p> : null}
                    </div>

                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                            className="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                        />
                        Remember me
                    </label>

                    <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                        {processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </form>

                <div className="mt-6 flex flex-col gap-2 text-sm text-slate-600">
                    <Link href="/forgot-password" className="text-cyan-700 hover:underline">
                        Forgot password?
                    </Link>
                    <p>
                        No account?{' '}
                        <Link href="/register" className="text-cyan-700 hover:underline">
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </GuestLayout>
    );
}
