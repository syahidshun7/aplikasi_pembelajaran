import DOMPurify from 'dompurify';

export const submissionContentToHtml = (value) => {
    const content = String(value ?? '');
    if (!/^\s*<(?:p|h2|h3|ul|ol|blockquote|pre)\b/i.test(content)) {
        return content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\r?\n/g, '<br>');
    }

    return DOMPurify.sanitize(content, {
        ALLOWED_TAGS: ['p', 'h2', 'h3', 'strong', 'em', 's', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'br', 'a'],
        ALLOWED_ATTR: ['href'],
    });
};

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
