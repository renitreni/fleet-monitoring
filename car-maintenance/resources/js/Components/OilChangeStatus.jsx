import { classNames } from '@/lib/utils';

const STATUS_CONFIG = {
    ok: {
        text: 'OK',
        bgColor: 'bg-green-100',
        textColor: 'text-green-800',
    },
    due_soon: {
        text: 'Due Soon',
        bgColor: 'bg-yellow-100',
        textColor: 'text-yellow-800',
    },
    overdue: {
        text: 'Overdue',
        bgColor: 'bg-red-100',
        textColor: 'text-red-800',
    },
    null: {
        text: 'Not Tracked',
        bgColor: 'bg-gray-100',
        textColor: 'text-gray-800',
    },
};

export default function OilChangeStatus({ status }) {
    const config = STATUS_CONFIG[status ?? 'null'];

    return (
        <span
            className={classNames(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                config.bgColor,
                config.textColor
            )}
        >
            {config.text}
        </span>
    );
}
