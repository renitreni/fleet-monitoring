import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MapDesignSettings from '@/Components/MapDesignSettings';

export default function Show({ settings, catalog, updateUrl }) {
    return (
        <AuthenticatedLayout
            title="Map settings"
            header={
                <div>
                    <p className="eyebrow">Administration</p>
                    <h1 className="mt-3 text-4xl font-black uppercase">Map settings</h1>
                    <p className="mt-3 text-sm text-[var(--text-muted)]">
                        Control the map designs available across Motologic.
                    </p>
                </div>
            }
        >
            <div className="mx-auto max-w-4xl px-5 py-8 sm:px-8">
                <MapDesignSettings settings={settings} catalog={catalog} updateUrl={updateUrl} />
            </div>
        </AuthenticatedLayout>
    );
}
