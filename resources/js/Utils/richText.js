export const richTextToPlainText = (value, fallback = '') => {
    const html = String(value ?? '').trim();

    if (html === '') {
        return fallback;
    }

    if (typeof DOMParser !== 'undefined') {
        const document = new DOMParser().parseFromString(html, 'text/html');
        const text = String(document.body?.textContent || '').replace(/\s+/g, ' ').trim();

        return text || fallback;
    }

    return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim() || fallback;
};
