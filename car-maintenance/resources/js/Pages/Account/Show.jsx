import { useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import ErrorMessage from '@/Components/ErrorMessage';
import Label from '@/Components/Label';

export default function Show({ nameChangeQuota, passwordResetToken, passwordStatus }) {
    const user = usePage().props.auth.user;
    const form = useForm({
        name: user.name,
        email: user.email,
        country: user.country,
    });
    const emailForm = useForm({});
    const passwordForm = useForm({ password: '', password_confirmation: '' });

    function sendVerification(event) {
        event.preventDefault();
        emailForm.post('/account/password/email', { errorBag: 'passwordEmail', preserveScroll: true });
    }

    function changePassword(event) {
        event.preventDefault();
        passwordForm
            .transform((data) => ({ ...data, token: passwordResetToken }))
            .put('/account/password', {
                errorBag: 'accountPassword',
                preserveScroll: true,
                onFinish: () => passwordForm.reset(),
            });
    }

    const limitReached = nameChangeQuota.remaining === 0;

    function submit(event) {
        event.preventDefault();
        const name = form.data.name.trim();

        form.transform((data) => ({ ...data, name })).put('/user/profile-information', {
            errorBag: 'updateProfileInformation',
            preserveScroll: true,
            onSuccess: () => {
                form.setData('name', name);
                form.setDefaults('name', name);
            },
        });
    }

    return (
        <AuthenticatedLayout
            title="Account"
            header={
                <div>
                    <p className="eyebrow">Profile</p>
                    <h1 className="mt-3 text-4xl font-black uppercase">Account</h1>
                </div>
            }
        >
            <div className="mx-auto max-w-3xl px-5 py-8 sm:px-8">
                <section className="border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-8">
                    <h2 className="text-2xl font-black uppercase">Display name</h2>
                    <p className="mt-3 text-sm leading-6 text-[var(--text-muted)]">
                        This is the name shown on leaderboards and around Motologic. You can use a nickname or event
                        name, whether you signed up with Google or email.
                    </p>

                    <div className="mt-6 border border-[var(--border)] bg-[var(--surface-muted)] p-4 text-sm">
                        <span className="font-bold">
                            {nameChangeQuota.remaining} of {nameChangeQuota.limit} changes remaining
                        </span>{' '}
                        <span className="text-[var(--text-muted)]">in the last 12 months.</span>
                        {limitReached && nameChangeQuota.next_available_at && (
                            <span className="mt-1 block text-[var(--text-muted)]">
                                Your next change becomes available on{' '}
                                {new Date(nameChangeQuota.next_available_at).toLocaleDateString()}.
                            </span>
                        )}
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <Label htmlFor="name" value="Display name" />
                            <input
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                maxLength={255}
                                autoComplete="name"
                                disabled={limitReached}
                                className="mt-2 w-full border border-[var(--border)] bg-[var(--background)] px-4 py-3 text-[var(--text)] focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)] disabled:opacity-60"
                            />
                            <ErrorMessage message={form.errors.name} />
                        </div>

                        <div>
                            <Label htmlFor="account-email" value="Login email" />
                            <input
                                id="account-email"
                                value={user.email}
                                readOnly
                                className="mt-2 w-full border border-[var(--border)] bg-[var(--surface-muted)] px-4 py-3 text-[var(--text-muted)]"
                            />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button processing={form.processing} disabled={!form.isDirty || limitReached}>
                                Save display name
                            </Button>
                            {form.recentlySuccessful && <p className="text-sm text-green-600">Display name updated.</p>}
                        </div>
                    </form>
                </section>
                <section className="mt-6 border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-8">
                    <h2 className="text-2xl font-black uppercase">Change password</h2>
                    <p className="mt-3 text-sm leading-6 text-[var(--text-muted)]">
                        First, verify your email using a link sent to {user.email}. Your password only changes after you
                        open the link and save a new password.
                    </p>
                    {passwordStatus && (
                        <p role="status" className="mt-4 text-sm">
                            {passwordStatus}
                        </p>
                    )}
                    {passwordResetToken && (
                        <form onSubmit={changePassword} className="mt-6 space-y-5">
                            {[
                                ['password', 'New password'],
                                ['password_confirmation', 'Confirm new password'],
                            ].map(([field, label]) => (
                                <div key={field}>
                                    <Label htmlFor={field} value={label} />
                                    <input
                                        id={field}
                                        name={field}
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                        minLength={8}
                                        value={passwordForm.data[field]}
                                        onChange={(event) => passwordForm.setData(field, event.target.value)}
                                        className="mt-2 w-full border border-[var(--border)] bg-[var(--background)] px-4 py-3 text-[var(--text)] focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]"
                                    />
                                    <ErrorMessage message={passwordForm.errors[field]} />
                                </div>
                            ))}
                            <ErrorMessage message={passwordForm.errors.token} />
                            <Button processing={passwordForm.processing}>Save new password</Button>
                        </form>
                    )}
                    <form onSubmit={sendVerification} className="mt-6 space-y-3">
                        <ErrorMessage message={emailForm.errors.email} />
                        <Button processing={emailForm.processing}>
                            {passwordResetToken ? 'Send a new verification email' : 'Send verification email'}
                        </Button>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
