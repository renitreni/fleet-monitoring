import { GoogleIcon } from './OAuthIcons';

export default function SocialAuthButtons() {
    return (
        <div className="mt-8">
            <div className="relative">
                <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-white/10" />
                </div>
                <div className="relative flex justify-center text-xs font-bold uppercase tracking-[0.16em]">
                    <span className="bg-[#101316] px-4 text-white/35">Or continue with</span>
                </div>
            </div>

            <div className="mt-5">
                <a
                    href="/auth/google"
                    className="inline-flex h-12 w-full items-center justify-center gap-3 border border-white/15 bg-white/[0.03] px-4 text-sm font-bold text-white/75 transition hover:border-white/30 hover:bg-white/[0.07] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#ee2b24] focus:ring-offset-2 focus:ring-offset-[#101316]"
                >
                    <GoogleIcon />
                    Continue with Google
                </a>
            </div>
        </div>
    );
}
