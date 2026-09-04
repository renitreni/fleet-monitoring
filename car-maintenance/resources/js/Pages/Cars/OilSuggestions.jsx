import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import EmptyState from '@/Components/EmptyState';

function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
        </svg>
    );
}

export default function OilSuggestions({ car, suggestion }) {
    const [generating, setGenerating] = useState(false);

    const generate = () => {
        if (generating) return;

        setGenerating(true);
        router.post(
            `/cars/${car.id}/oil-suggestions/generate`,
            {},
            {
                onFinish: () => setGenerating(false),
            }
        );
    };

    const formatDateTime = (value) =>
        new Date(value).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

    const data = suggestion?.suggestions_json ?? null;
    const products = Array.isArray(data?.products)
        ? data.products.slice(0, 3)
        : Array.isArray(data?.brands)
          ? data.brands.slice(0, 3).map((brand, index) => {
                const legacyBrand = typeof brand === 'object' && brand !== null ? brand : { name: String(brand) };

                return {
                    brand: legacyBrand.name,
                    product: legacyBrand.product || legacyBrand.name,
                    role: index === 0 ? 'Assigned product' : 'Alternative',
                    reason: legacyBrand.notes || legacyBrand.reason,
                };
            })
          : [];

    const specs = data
        ? [
              { label: 'Viscosity', value: data.viscosity },
              { label: 'Oil Type', value: data.oil_type },
              { label: 'Specification', value: data.specification },
              {
                  label: 'Capacity',
                  value: data.capacity_liters ? `${data.capacity_liters} liters` : null,
              },
              {
                  label: 'Service Interval',
                  value:
                      data.interval_months && data.interval_kilometers
                          ? `${data.interval_months} months or ${Number(data.interval_kilometers).toLocaleString()} km, whichever comes first`
                          : null,
              },
          ].filter((item) => item.value)
        : [];

    return (
        <AuthenticatedLayout
            title={`Oil recommendations for ${car.year} ${car.make} ${car.model}`}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Oil Suggestions — {car.year} {car.make} {car.model}
                </h2>
            }
        >
            <div className="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <Link href={`/cars/${car.id}`} className="text-sm text-gray-600 underline hover:text-gray-900">
                        &larr; Back to {car.year} {car.make} {car.model}
                    </Link>
                </div>

                {data ? (
                    <div className="space-y-6">
                        {/* Recommended specification */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-gray-900">Recommended Oil Specification</h3>
                            </div>
                            <div className="p-6">
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    {specs.map((item) => (
                                        <div key={item.label} className="rounded-md bg-gray-50 p-4">
                                            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                                {item.label}
                                            </dt>
                                            <dd className="mt-1 text-sm font-semibold text-gray-900">{item.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        </div>

                        {data.interval_basis && (
                            <div className="rounded-md border border-blue-200 bg-blue-50 p-4">
                                <p className="text-xs font-medium uppercase tracking-wide text-blue-700">
                                    Interval basis
                                </p>
                                <p className="mt-1 text-sm text-blue-900">{data.interval_basis}</p>
                            </div>
                        )}

                        {/* Recommended products */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-gray-900">Recommended Products</h3>
                            </div>
                            <div className="p-6">
                                {products.length > 0 ? (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {products.map((product, index) => (
                                            <div
                                                key={`${product.brand}-${product.product}-${index}`}
                                                className="rounded-md border border-gray-200 p-4"
                                            >
                                                <p className="text-xs font-medium uppercase tracking-wide text-[var(--accent)]">
                                                    {product.role || (index === 0 ? 'Assigned product' : 'Alternative')}
                                                </p>
                                                <p className="mt-1 text-sm font-semibold text-gray-900">
                                                    {product.product}
                                                </p>
                                                {product.brand && (
                                                    <p className="mt-1 text-sm text-gray-600">{product.brand}</p>
                                                )}
                                                {product.reason && (
                                                    <p className="mt-2 text-sm text-gray-600">{product.reason}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-600">No product recommendations were returned.</p>
                                )}
                            </div>
                        </div>

                        {/* Notes */}
                        {data.notes && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-lg font-semibold text-gray-900">Notes</h3>
                                </div>
                                <div className="p-6">
                                    <p className="text-sm leading-relaxed text-gray-700">{data.notes}</p>
                                </div>
                            </div>
                        )}

                        <p className="text-xs text-gray-500">
                            Generated on {formatDateTime(suggestion.created_at)}. Suggestions are cached permanently for
                            this vehicle specification to limit AI usage — they cannot be regenerated.
                        </p>
                    </div>
                ) : generating ? (
                    <div
                        className="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <div className="flex min-h-64 flex-col items-center justify-center px-6 py-12 text-center">
                            <svg
                                className="h-10 w-10 animate-spin text-[var(--accent)]"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    className="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    strokeWidth="4"
                                />
                                <path
                                    className="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"
                                />
                            </svg>
                            <h3 className="mt-5 text-lg font-semibold text-gray-900">
                                Getting maintenance suggestions…
                            </h3>
                            <p className="mt-2 max-w-md text-sm text-gray-600">
                                OpenRouter is preparing oil and service-interval recommendations. This may take a
                                moment; please keep this page open.
                            </p>
                        </div>
                    </div>
                ) : (
                    <EmptyState
                        icon="🛢️"
                        title="No oil suggestions yet"
                        description={`Get AI-generated oil and service-interval recommendations for your ${car.year} ${car.make} ${car.model}. Results are cached permanently for this vehicle specification, so the AI is only asked once.`}
                        action={
                            <Button type="button" processing={generating} onClick={generate}>
                                {generating ? (
                                    <>
                                        <Spinner />
                                        <span className="ml-2">Getting suggestions…</span>
                                    </>
                                ) : (
                                    'Get Maintenance Suggestions'
                                )}
                            </Button>
                        }
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
