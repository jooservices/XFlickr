import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/Button';
import Input from '@/Components/Input';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/register');
    }

    return (
        <GuestLayout>
            <Head title="Create account" />

            <div className="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-slate-900">Create account</h1>
                <p className="mt-1 text-sm text-slate-600">
                    New accounts stay inactive until an administrator activates them.
                </p>

                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <div>
                        <label htmlFor="name" className="mb-1 block text-sm font-medium text-slate-700">
                            Name
                        </label>
                        <Input
                            id="name"
                            type="text"
                            autoComplete="name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            required
                        />
                        {errors.name ? <p className="mt-1 text-sm text-red-600">{errors.name}</p> : null}
                    </div>

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
                        {processing ? 'Creating…' : 'Create account'}
                    </Button>
                </form>

                <p className="mt-6 text-sm text-slate-600">
                    Already have an account?{' '}
                    <Link href="/login" className="text-cyan-700 hover:underline">
                        Sign in
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
