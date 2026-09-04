import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CarForm from '@/Components/CarForm';

export default function Create({ carAllowance }) {
    const { data, setData, post, processing, errors } = useForm({
        make: '',
        model: '',
        year: '',
        current_mileage: '',
        country: '',
        vin: '',
    });

    return (
        <AuthenticatedLayout
            title="Add a car"
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Add a Car</h2>}
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        {carAllowance.limit !== null && (
                            <div className="mb-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                Free accounts can add {carAllowance.limit} cars per week. You have{' '}
                                {carAllowance.remaining} {carAllowance.remaining === 1 ? 'car' : 'cars'} remaining this
                                week.
                            </div>
                        )}
                        <CarForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing || carAllowance.remaining === 0}
                            onSubmit={() => post('/cars')}
                            submitLabel={carAllowance.remaining === 0 ? 'Weekly Limit Reached' : 'Add Car'}
                            cancelHref="/cars"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
