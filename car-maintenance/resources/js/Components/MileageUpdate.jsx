import { useState } from 'react';
import { router } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/Button';

export default function MileageUpdate({ car, onSuccess }) {
    const [newMileage, setNewMileage] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (processing) return;

        setProcessing(true);
        setErrors({});

        router.put(
            `/cars/${car.id}/mileage`,
            {
                current_mileage: parseInt(newMileage),
            },
            {
                preserveScroll: true,
                onError: (errs) => {
                    setErrors(errs);
                    setProcessing(false);
                },
                onSuccess: (page) => {
                    // Find the updated car in the page props
                    const updatedCar = page.props.car;
                    if (onSuccess && updatedCar) {
                        onSuccess(updatedCar);
                    }
                    setNewMileage('');
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <div className="mt-6">
            <h3 className="text-lg font-bold uppercase text-[var(--text)]">Update Current Mileage</h3>
            <p className="mb-4 mt-1 text-sm text-[var(--text-muted)]">
                Update your vehicle&apos;s current mileage. This will automatically recalculate your oil change status.
            </p>

            <form onSubmit={handleSubmit}>
                <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                    <div className="flex-1">
                        <TextInput
                            id="current_mileage"
                            name="current_mileage"
                            type="number"
                            value={newMileage}
                            onChange={(e) => setNewMileage(e.target.value)}
                            min={car.current_mileage + 1}
                            placeholder={`Enter mileage > ${car.current_mileage.toLocaleString()}`}
                            error={errors.current_mileage}
                            className="w-full"
                            required
                        />
                        {errors.current_mileage && (
                            <div className="mt-1 text-sm text-red-600">{errors.current_mileage}</div>
                        )}
                    </div>

                    <Button
                        type="submit"
                        disabled={processing || !newMileage || parseInt(newMileage) <= car.current_mileage}
                        processing={processing}
                    >
                        Update Mileage
                    </Button>
                </div>
            </form>
        </div>
    );
}
