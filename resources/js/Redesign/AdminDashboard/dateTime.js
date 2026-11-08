/** Format a server timestamp for a datetime-local input in the app's timezone. */
export const toAppDateTimeLocal = (value, timeZone) => {
    if (!value) return '';

    // Laravel may also send a timezone-free database value. It is already app-local.
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/.test(value)) {
        return value.replace(' ', 'T').slice(0, 16);
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
        timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map(({ type, value: part }) => [type, part]));

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};
