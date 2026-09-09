import { useForm } from '@inertiajs/react';
import Button from '@/Components/Button';

export default function MapDesignSettings({ settings, catalog, updateUrl }) {
    const form = useForm(settings);
    function toggle(id) {
        const enabled = form.data.enabled.includes(id)
            ? form.data.enabled.filter((value) => value !== id)
            : [...form.data.enabled, id];
        form.setData({
            enabled,
            default: enabled.includes(form.data.default) ? form.data.default : (enabled[0] ?? ''),
        });
    }
    function submit(event) {
        event.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    }
    return (
        <form onSubmit={submit} className="space-y-5 border border-[var(--border)] bg-[var(--surface)] p-5">
            <div>
                <h2 className="text-xl font-black uppercase">Map designs</h2>
                <p className="mt-2 text-sm text-[var(--text-muted)]">
                    Choose which themes everyone can use on route and trip maps. Changes apply on their next refresh.
                </p>
            </div>
            <fieldset disabled={form.processing} className="grid gap-3 sm:grid-cols-3">
                <legend className="sr-only">Available map designs</legend>
                {catalog.map((design) => (
                    <label key={design.id} className="flex items-start gap-3 border border-[var(--border)] p-4">
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={form.data.enabled.includes(design.id)}
                            disabled={form.data.enabled.length === 1 && form.data.enabled.includes(design.id)}
                            onChange={() => toggle(design.id)}
                        />
                        <span>
                            <span className="font-bold">{design.name}</span>
                            <span className="mt-1 block text-sm text-[var(--text-muted)]">{design.description}</span>
                        </span>
                    </label>
                ))}
            </fieldset>
            <label className="flex flex-wrap items-center gap-3 text-sm font-bold">
                Default design
                <select
                    value={form.data.default}
                    disabled={form.processing}
                    onChange={(event) => form.setData('default', event.target.value)}
                    className="border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
                >
                    {catalog
                        .filter((design) => form.data.enabled.includes(design.id))
                        .map((design) => (
                            <option key={design.id} value={design.id}>
                                {design.name}
                            </option>
                        ))}
                </select>
            </label>
            {Object.entries(form.errors).map(([key, error]) => (
                <p key={key} role="alert" className="text-sm text-red-600">
                    {error}
                </p>
            ))}
            <div className="flex items-center gap-3">
                <Button disabled={form.processing}>{form.processing ? 'Saving…' : 'Save map designs'}</Button>
                {form.recentlySuccessful && (
                    <p role="status" className="text-sm">
                        Map designs saved.
                    </p>
                )}
            </div>
        </form>
    );
}
