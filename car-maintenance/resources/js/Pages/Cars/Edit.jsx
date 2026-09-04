import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CarForm from '@/Components/CarForm';

export default function Edit({ car }) {
    const { data, setData, put, processing, errors } = useForm({
        make: car.make,
        model: car.model,
        year: car.year,
        current_mileage: car.current_mileage,
        country: car.country,
        vin: car.vin ?? '',
    });

    return (
        <AuthenticatedLayout
            title={`Edit ${car.year} ${car.make} ${car.model}`}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit {car.year} {car.make} {car.model}
                </h2>
            }
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        <CarForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={() => put(`/cars/${car.id}`)}
                            submitLabel="Save Changes"
                            cancelHref={`/cars/${car.id}`}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
