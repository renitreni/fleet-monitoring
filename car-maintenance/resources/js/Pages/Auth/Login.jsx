import { useForm, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import TextInput from '@/Components/TextInput';
import Label from '@/Components/Label';
import ErrorMessage from '@/Components/ErrorMessage';
import Button from '@/Components/Button';
import SocialAuthButtons from '@/Components/SocialAuthButtons';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout title="Sign in">
            <div className="mb-8">
                <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#ee2b24]">Welcome back</p>
                <h1 className="mt-3 text-4xl font-black uppercase tracking-[-0.045em] text-white">Sign in</h1>
                <p className="mt-3 text-base leading-7 text-white/45">
                    Pick up where you left off and keep your garage drive ready.
                </p>
            </div>

            {status && (
                <div className="mb-5 border border-green-400/20 bg-green-400/10 px-4 py-3 text-sm font-medium text-green-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <Label htmlFor="email" value="Email address" className="text-sm font-bold text-white/70" />
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoFocus
                        autoComplete="username"
                        variant="auth"
                    />
                    <ErrorMessage message={errors.email} />
                </div>

                <div>
                    <div className="flex items-center justify-between gap-4">
                        <Label htmlFor="password" value="Password" className="text-sm font-bold text-white/70" />
                        <Link
                            href="/forgot-password"
                            className="text-sm font-semibold text-white/45 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-[#ee2b24]"
                        >
                            Forgot password?
                        </Link>
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                        autoComplete="current-password"
                        variant="auth"
                    />
                    <ErrorMessage message={errors.password} />
                </div>

                <div>
                    <label className="flex w-fit items-center gap-3 text-sm text-white/55">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="h-4 w-4 border-white/25 bg-black/25 text-[#ee2b24] focus:ring-[#ee2b24] focus:ring-offset-[#101316]"
                        />
                        <span>Remember me</span>
                    </label>
                </div>

                <div className="pt-1">
                    <Button
                        processing={processing}
                        variant="auth"
                        className="h-12 w-full justify-center rounded-none text-sm tracking-[0.16em]"
                    >
                        Log in
                    </Button>
                </div>
            </form>

            <SocialAuthButtons />

            <div className="mt-7 text-center text-sm text-white/45">
                Don&apos;t have an account?{' '}
                <Link
                    href="/register"
                    className="font-bold text-white underline decoration-[#ee2b24] decoration-2 underline-offset-4 transition hover:text-[#ee2b24]"
                >
                    Create one
                </Link>
            </div>
        </GuestLayout>
    );
}
