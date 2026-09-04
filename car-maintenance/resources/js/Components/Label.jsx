export default function Label({ htmlFor, value, className = '', children }) {
    return (
        <label htmlFor={htmlFor} className={'block text-sm font-medium text-[var(--text)] ' + className}>
            {value || children}
        </label>
    );
}
