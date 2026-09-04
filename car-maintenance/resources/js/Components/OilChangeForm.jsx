import { useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '@/Components/Button';
import Label from '@/Components/Label';
import TextInput from '@/Components/TextInput';
import ErrorMessage from '@/Components/ErrorMessage';

export default function OilChangeForm({ car, oilChange = null, recommendedInterval = null, onClose }) {
    const [formState, setFormState] = useState({
        last_changed_at: oilChange?.last_changed_at || '',
        last_changed_mileage: oilChange?.last_changed_mileage || car.current_mileage,
        interval_months: oilChange?.interval_months || recommendedInterval?.interval_months || 6,
        interval_mileage: oilChange?.interval_mileage || recommendedInterval?.interval_kilometers || 5000,
    });

    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const isEditing = !!oilChange;

    const handleChange = (field, value) => {
        setFormState((prev) => ({ ...prev, [field]: value }));
        // Clear error when user starts typing
        if (errors[field]) {
            setErrors((prev) => ({ ...prev, [field]: null }));
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);

        const url = isEditing ? `/cars/${car.id}/oil-changes/${oilChange.id}` : `/cars/${car.id}/oil-changes`;

        const method = isEditing ? 'put' : 'post';

        router[method](url, formState, {
            preserveScroll: true,
            onSuccess: () => {
                if (onClose) {
                    onClose();
                }
            },
            onError: (err) => {
                setErrors(err);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {!oilChange && recommendedInterval?.interval_months && recommendedInterval?.interval_kilometers && (
                <p className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                    Prefilled from the AI recommendation. Confirm it against the owner&apos;s manual before saving.
                </p>
            )}
            {/* Last Changed Date */}
            <div>
                <Label htmlFor="last_changed_at">Last Changed Date</Label>
                <TextInput
                    id="last_changed_at"
                    type="date"
                    value={formState.last_changed_at}
                    onChange={(e) => handleChange('last_changed_at', e.target.value)}
                    className={errors.last_changed_at ? 'border-red-300' : ''}
                    max={new Date().toISOString().split('T')[0]} // Today's date as max
                />
                {errors.last_changed_at && <ErrorMessage message={errors.last_changed_at} />}
            </div>

            {/* Last Changed Mileage */}
            <div>
                <Label htmlFor="last_changed_mileage">Last Changed Mileage</Label>
                <TextInput
                    id="last_changed_mileage"
                    type="number"
                    value={formState.last_changed_mileage}
                    onChange={(e) => handleChange('last_changed_mileage', e.target.value)}
                    min="0"
                    className={errors.last_changed_mileage ? 'border-red-300' : ''}
                />
                {errors.last_changed_mileage && <ErrorMessage message={errors.last_changed_mileage} />}
            </div>

            {/* Interval Months */}
            <div>
                <Label htmlFor="interval_months">Service Interval (Months)</Label>
                <TextInput
                    id="interval_months"
                    type="number"
                    value={formState.interval_months}
                    onChange={(e) => handleChange('interval_months', e.target.value)}
                    min="1"
                    max="24"
                    className={errors.interval_months ? 'border-red-300' : ''}
                />
                {errors.interval_months && <ErrorMessage message={errors.interval_months} />}
            </div>

            {/* Interval Mileage */}
            <div>
                <Label htmlFor="interval_mileage">Service Interval (Kilometers)</Label>
                <TextInput
                    id="interval_mileage"
                    type="number"
                    value={formState.interval_mileage}
                    onChange={(e) => handleChange('interval_mileage', e.target.value)}
                    min="100"
                    max="50000"
                    className={errors.interval_mileage ? 'border-red-300' : ''}
                />
                {errors.interval_mileage && <ErrorMessage message={errors.interval_mileage} />}
            </div>

            <div className="flex items-center justify-end gap-3 pt-4">
                {onClose && (
                    <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {processing
                        ? isEditing
                            ? 'Updating...'
                            : 'Recording...'
                        : isEditing
                          ? 'Update Oil Change'
                          : 'Record Oil Change'}
                </Button>
            </div>
        </form>
    );
}
