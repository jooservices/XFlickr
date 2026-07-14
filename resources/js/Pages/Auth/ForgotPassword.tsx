import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';

type ForgotPasswordPageProps = {
    status?: string | null;
    resetUrl?: string | null;
};

export default function ForgotPassword({ status = null, resetUrl = null }: ForgotPasswordPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/forgot-password');
    }

    return (
        <GuestLayout>
            <Head title="Forgot password" />

            <div className="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-slate-900">Forgot password</h1>
                <p className="mt-1 text-sm text-slate-600">
                    Email delivery is not enabled yet. For active accounts we generate a reset URL and store a
                    hashed token in <code className="text-xs">password_reset_tokens</code>.
                </p>

                {status ? (
                    <p className="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                        {status}
                    </p>
                ) : null}

                {resetUrl ? (
                    <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                        <p className="font-medium">Reset URL (copy now):</p>
                        <p className="mt-1 break-all font-mono text-xs">{resetUrl}</p>
                    </div>
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

                    <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                        {processing ? 'Generating…' : 'Generate reset link'}
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
