import { useForm, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import TextInput from '@/Components/TextInput';
import Label from '@/Components/Label';
import ErrorMessage from '@/Components/ErrorMessage';
import Button from '@/Components/Button';
import SocialAuthButtons from '@/Components/SocialAuthButtons';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        country: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout title="Create account">
            <div className="mb-8">
                <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#ee2b24]">Join Motologiq</p>
                <h1 className="mt-3 text-4xl font-black uppercase tracking-[-0.045em] text-white">Create account</h1>
                <p className="mt-3 text-base leading-7 text-white/45">
                    Build a clear maintenance record for every car you own.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="name" value="Full name" className="text-sm font-bold text-white/70" />
                        <TextInput
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            autoComplete="name"
                            variant="auth"
                        />
                        <ErrorMessage message={errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="country" value="Country code" className="text-sm font-bold text-white/70" />
                        <TextInput
                            id="country"
                            type="text"
                            maxLength={2}
                            value={data.country}
                            onChange={(e) => setData('country', e.target.value.toUpperCase())}
                            required
                            autoComplete="country"
                            placeholder="PH"
                            variant="auth"
                        />
                        <ErrorMessage message={errors.country} />
                    </div>
                </div>

                <div>
                    <Label htmlFor="email" value="Email address" className="text-sm font-bold text-white/70" />
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        variant="auth"
                    />
                    <ErrorMessage message={errors.email} />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="password" value="Password" className="text-sm font-bold text-white/70" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            autoComplete="new-password"
                            variant="auth"
                        />
                        <ErrorMessage message={errors.password} />
                    </div>

                    <div>
                        <Label
                            htmlFor="password_confirmation"
                            value="Confirm password"
                            className="text-sm font-bold text-white/70"
                        />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                            autoComplete="new-password"
                            variant="auth"
                        />
                        <ErrorMessage message={errors.password_confirmation} />
                    </div>
                </div>

                <div className="pt-1">
                    <Button
                        processing={processing}
                        variant="auth"
                        className="h-12 w-full justify-center rounded-none text-sm tracking-[0.16em]"
                    >
                        Register
                    </Button>
                </div>
            </form>

            <SocialAuthButtons />

            <div className="mt-7 text-center text-sm text-white/45">
                Already registered?{' '}
                <Link
                    href="/login"
                    className="font-bold text-white underline decoration-[#ee2b24] decoration-2 underline-offset-4 transition hover:text-[#ee2b24]"
                >
                    Sign in
                </Link>
            </div>
        </GuestLayout>
    );
}
