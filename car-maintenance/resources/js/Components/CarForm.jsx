import { Link } from '@inertiajs/react';
import Button from '@/Components/Button';
import ErrorMessage from '@/Components/ErrorMessage';
import Label from '@/Components/Label';
import TextInput from '@/Components/TextInput';

export default function CarForm({
    data,
    setData,
    errors,
    processing = false,
    onSubmit,
    submitLabel = 'Save Car',
    cancelHref,
}) {
    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit();
    };

    return (
        <form onSubmit={handleSubmit}>
            <div>
                <Label htmlFor="make" value="Make" />
                <TextInput
                    id="make"
                    value={data.make}
                    onChange={(e) => setData('make', e.target.value)}
                    required
                    autoFocus
                />
                <ErrorMessage message={errors.make} />
            </div>

            <div className="mt-4">
                <Label htmlFor="model" value="Model" />
                <TextInput id="model" value={data.model} onChange={(e) => setData('model', e.target.value)} required />
                <ErrorMessage message={errors.model} />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="year" value="Year" />
                    <TextInput
                        id="year"
                        type="number"
                        min="1900"
                        max={new Date().getFullYear() + 1}
                        value={data.year}
                        onChange={(e) => setData('year', e.target.value)}
                        required
                    />
                    <ErrorMessage message={errors.year} />
                </div>

                <div>
                    <Label htmlFor="current_mileage" value="Current Mileage" />
                    <TextInput
                        id="current_mileage"
                        type="number"
                        min="0"
                        step="1"
                        value={data.current_mileage}
                        onChange={(e) => setData('current_mileage', e.target.value)}
                        required
                    />
                    <ErrorMessage message={errors.current_mileage} />
                </div>
            </div>

            <div className="mt-4">
                <Label htmlFor="country" value="Country (2-letter code)" />
                <TextInput
                    id="country"
                    maxLength={2}
                    value={data.country}
                    onChange={(e) => setData('country', e.target.value.toUpperCase())}
                    required
                />
                <ErrorMessage message={errors.country} />
            </div>

            <div className="mt-4">
                <Label htmlFor="vin" value="VIN (optional)" />
                <TextInput
                    id="vin"
                    maxLength={17}
                    value={data.vin}
                    onChange={(e) => setData('vin', e.target.value.toUpperCase())}
                />
                <ErrorMessage message={errors.vin} />
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                {cancelHref && (
                    <Link
                        href={cancelHref}
                        className="text-sm font-bold text-[var(--text-muted)] underline decoration-[var(--accent)] underline-offset-4 hover:text-[var(--text)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                    >
                        Cancel
                    </Link>
                )}
                <Button processing={processing}>{submitLabel}</Button>
            </div>
        </form>
    );
}
