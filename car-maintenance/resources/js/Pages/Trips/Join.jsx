import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import ErrorMessage from '@/Components/ErrorMessage';
import TripPrivacy from '@/Components/TripPrivacy';

export default function Join({ trip, joinUrl }) {
    const form = useForm({ public_consent: false });
    return (
        <AuthenticatedLayout title="Join a road trip">
            <div className="mx-auto max-w-2xl space-y-6 px-5 py-12">
                <div className="border border-[var(--border)] bg-[var(--surface)] p-8">
                    <p className="eyebrow">You’re invited</p>
                    <h1 className="mt-4 text-4xl font-black uppercase">{trip.name}</h1>
                    <p className="mt-3 text-sm text-[var(--text-muted)]">
                        Route by {trip.creator} · {trip.distance_km} km · {trip.checkpoint_count} required checkpoints
                    </p>
                    <p className="mt-6">{trip.description}</p>
                    <form
                        className="mt-8 space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(joinUrl);
                        }}
                    >
                        <p className="text-sm text-[var(--text-muted)]">
                            Join the group now. You decide when to share your location using Start tracking on the next
                            screen.
                        </p>
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.public_consent}
                                onChange={(event) => form.setData('public_consent', event.target.checked)}
                            />
                            <span>
                                Show my account name, initial avatar, progress, and completion on the landing-page
                                leaderboard when the organizer makes this trip public. I can withdraw this anytime.
                            </span>
                        </label>
                        <ErrorMessage message={form.errors.public_consent} />
                        <Button processing={form.processing}>Join trip</Button>
                    </form>
                </div>
                <TripPrivacy />
            </div>
        </AuthenticatedLayout>
    );
}
