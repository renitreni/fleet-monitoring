import Button from '@/Components/Button';

export default function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel = 'Confirm',
    processing = false,
    onConfirm,
    onClose,
}) {
    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-title"
        >
            <div className="w-full max-w-md border border-[var(--border)] bg-[var(--surface)] p-6 text-[var(--text)] shadow-xl">
                <h3 id="confirm-dialog-title" className="text-lg font-bold uppercase text-[var(--text)]">
                    {title}
                </h3>
                <p className="mt-2 text-sm text-[var(--text-muted)]">{message}</p>

                <div className="mt-6 flex justify-end gap-3">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="button" variant="danger" processing={processing} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
