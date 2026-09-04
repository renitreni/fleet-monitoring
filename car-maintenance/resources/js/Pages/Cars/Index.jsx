import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';

const primaryLinkClasses =
    'inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

const secondaryLinkClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

export default function Index({ cars }) {
    const [carToDelete, setCarToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (!carToDelete) {
            return;
        }

        setDeleting(true);
        router.delete(`/cars/${carToDelete.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <AuthenticatedLayout
            title="My cars"
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">My Cars</h2>}
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="flex justify-end">
                    <Link href="/cars/create" className={primaryLinkClasses}>
                        Add Car
                    </Link>
                </div>

                {cars.length === 0 ? (
                    <EmptyState
                        className="mt-6"
                        icon="🚗"
                        title="No cars yet"
                        description="Add your first car to start tracking its maintenance."
                        action={
                            <Link href="/cars/create" className={primaryLinkClasses}>
                                Add Your First Car
                            </Link>
                        }
                    />
                ) : (
                    <div className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {cars.map((car) => (
                            <div key={car.id} className="rounded-lg bg-white p-6 shadow-sm">
                                <div className="flex items-start justify-between gap-2">
                                    <Link
                                        href={`/cars/${car.id}`}
                                        className="text-lg font-semibold text-gray-900 hover:text-indigo-600"
                                    >
                                        {car.year} {car.make}
                                    </Link>
                                    <span className="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                        {car.country}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-600">{car.model}</p>

                                <dl className="mt-4 space-y-1 text-sm text-gray-600">
                                    <div className="flex justify-between">
                                        <dt>Year</dt>
                                        <dd className="font-medium text-gray-900">{car.year}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt>Mileage</dt>
                                        <dd className="font-medium text-gray-900">
                                            {car.current_mileage.toLocaleString()}
                                        </dd>
                                    </div>
                                    {car.vin && (
                                        <div className="flex justify-between">
                                            <dt>VIN</dt>
                                            <dd className="font-mono text-xs text-gray-900">{car.vin}</dd>
                                        </div>
                                    )}
                                </dl>

                                <div className="mt-6 flex gap-2">
                                    <Link href={`/cars/${car.id}`} className={secondaryLinkClasses}>
                                        View
                                    </Link>
                                    <Link href={`/cars/${car.id}/edit`} className={secondaryLinkClasses}>
                                        Edit
                                    </Link>
                                    <Button variant="danger" onClick={() => setCarToDelete(car)}>
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <ConfirmDialog
                    open={Boolean(carToDelete)}
                    title="Delete this car?"
                    message={
                        carToDelete
                            ? `This will permanently remove your ${carToDelete.year} ${carToDelete.make} ${carToDelete.model} from your garage.`
                            : ''
                    }
                    confirmLabel="Delete Car"
                    processing={deleting}
                    onConfirm={confirmDelete}
                    onClose={() => setCarToDelete(null)}
                />
            </div>
        </AuthenticatedLayout>
    );
}
