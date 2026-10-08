import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Keeps the JSON-LD schema script in <head> in sync with the current page's
 * `schema` prop on client-side navigations. The server renders the initial
 * script tag in app.blade.php (id="page-schema"); this component adopts it.
 */
export default function SchemaManager() {
    const schema = usePage().props.schema;

    useEffect(() => {
        let tag = document.getElementById('page-schema');

        if (schema) {
            if (!tag) {
                tag = document.createElement('script');
                tag.type = 'application/ld+json';
                tag.id = 'page-schema';
                document.head.appendChild(tag);
            }
            tag.textContent = JSON.stringify(schema);
        } else if (tag) {
            tag.remove();
        }
    }, [schema]);

    return null;
}
