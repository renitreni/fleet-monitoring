import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import OilChangeStatus from '@/Components/OilChangeStatus';
import OilChangeForm from '@/Components/OilChangeForm';
import MileageUpdate from '@/Components/MileageUpdate';

const secondaryLinkClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2';

export default function Show({ car, recommendedInterval }) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [showOilChangeForm, setShowOilChangeForm] = useState(false);
    const [editingOilChange, setEditingOilChange] = useState(null);
    const [currentCar, setCurrentCar] = useState(car);

    const latestOilChange = currentCar.latest_oil_change;
    const oilStatus = currentCar.oil_status;

    const confirmDelete = () => {
        setDeleting(true);
        router.delete(`/cars/${currentCar.id}`, {
            onFinish: () => setDeleting(false),
        });
    };

    const handleEditOilChange = (oilChange) => {
        setEditingOilChange(oilChange);
        setShowOilChangeForm(true);
    };

    const closeOilChangeForm = () => {
        setShowOilChangeForm(false);
        setEditingOilChange(null);
    };

    const handleMileageUpdate = (updatedCar) => {
        setCurrentCar(updatedCar);
    };

    const formatMileage = (mileage) => {
        return mileage ? mileage.toLocaleString() : '—';
    };

    const formatDate = (date) => {
        return date
            ? new Date(date).toLocaleDateString('en-US', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
              })
            : '—';
    };

    const details = [
        { label: 'Year', value: currentCar.year },
        { label: 'Current Mileage', value: currentCar.current_mileage.toLocaleString() },
        { label: 'Country', value: currentCar.country },
        { label: 'VIN', value: currentCar.vin || '—' },
    ];

    return (
        <AuthenticatedLayout
            title={`${currentCar.year} ${currentCar.make} ${currentCar.model}`}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {currentCar.year} {currentCar.make} {currentCar.model}
                </h2>
            }
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <Link href="/cars" className="text-sm text-gray-600 underline hover:text-gray-900">
                        &larr; Back to my cars
                    </Link>

                    <div className="flex gap-3">
                        <Link href={`/cars/${currentCar.id}/oil-suggestions`} className={secondaryLinkClasses}>
                            Oil Suggestions
                        </Link>
                        <Link href={`/cars/${currentCar.id}/edit`} className={secondaryLinkClasses}>
                            Edit
                        </Link>
                        <Button variant="danger" onClick={() => setConfirmingDelete(true)}>
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        <h3 className="text-lg font-semibold text-gray-900">Car details</h3>

                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            {details.map((item) => (
                                <div key={item.label} className="rounded-md bg-gray-50 p-4">
                                    <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        {item.label}
                                    </dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">{item.value}</dd>
                                </div>
                            ))}
                        </dl>

                        <MileageUpdate car={currentCar} onSuccess={handleMileageUpdate} />
                    </div>
                </div>

                {/* Oil Change Tracking Section */}
                <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div className="border-b border-gray-200 px-6 py-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Oil Change Tracking</h3>
                            <OilChangeStatus status={oilStatus} />
                        </div>
                    </div>

                    <div className="p-6">
                        {latestOilChange ? (
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-md bg-gray-50 p-4">
                                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Last Changed
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                                            {formatDate(latestOilChange.last_changed_at)} at{' '}
                                            {formatMileage(latestOilChange.last_changed_mileage)} km
                                        </dd>
                                    </div>

                                    <div className="rounded-md bg-gray-50 p-4">
                                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Service Intervals
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                                            Every {latestOilChange.interval_months} months or{' '}
                                            {formatMileage(latestOilChange.interval_mileage)} km
                                        </dd>
                                    </div>

                                    <div className="rounded-md bg-gray-50 p-4">
                                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Next Due Date
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                                            {formatDate(latestOilChange.next_due_date)}
                                        </dd>
                                    </div>

                                    <div className="rounded-md bg-gray-50 p-4">
                                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Next Due Mileage
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                                            {formatMileage(latestOilChange.next_due_mileage)} km
                                        </dd>
                                    </div>
                                </div>

                                <div className="flex gap-3 pt-2">
                                    <Button variant="secondary" onClick={() => handleEditOilChange(latestOilChange)}>
                                        Update Oil Change
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <EmptyState
                                icon="🛢️"
                                title="No oil changes recorded"
                                description="Record your first oil change to start tracking service intervals and due dates."
                                action={
                                    <Button onClick={() => setShowOilChangeForm(true)}>Record First Oil Change</Button>
                                }
                            />
                        )}
                    </div>
                </div>

                {/* Oil Change Form Modal */}
                {showOilChangeForm && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                            <div
                                className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                onClick={closeOilChangeForm}
                            ></div>

                            <div className="relative inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                                <div className="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                    <div className="sm:flex sm:items-start">
                                        <div className="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                            <h3 className="text-base font-semibold leading-6 text-gray-900">
                                                {editingOilChange ? 'Update Oil Change' : 'Record Oil Change'}
                                            </h3>

                                            <div className="mt-4">
                                                <OilChangeForm
                                                    car={currentCar}
                                                    oilChange={editingOilChange}
                                                    recommendedInterval={recommendedInterval}
                                                    onClose={closeOilChangeForm}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
                <ConfirmDialog
                    open={confirmingDelete}
                    title="Delete this car?"
                    message={`This will permanently remove your ${car.year} ${car.make} ${car.model} from your garage.`}
                    confirmLabel="Delete Car"
                    processing={deleting}
                    onConfirm={confirmDelete}
                    onClose={() => setConfirmingDelete(false)}
                />
            </div>
        </AuthenticatedLayout>
    );
}
