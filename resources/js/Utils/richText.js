import DOMPurify from 'dompurify';

const richTextOptions = {
    ALLOWED_TAGS: ['p', 'h2', 'h3', 'strong', 'em', 's', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'br', 'a'],
    ALLOWED_ATTR: ['href'],
};

export const submissionContentToHtml = (value) => {
    const content = String(value ?? '');
    if (!/^\s*<(?:p|h2|h3|ul|ol|blockquote|pre)\b/i.test(content)) {
        return content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\r?\n/g, '<br>');
    }

    return DOMPurify.sanitize(content, richTextOptions);
};

export const questInstructionsToHtml = (value) => {
    const content = String(value ?? '');
    const isHtml = /^\s*<(?:p|div|h[1-6]|ul|ol|li|br|blockquote|pre|strong|b|em|i|code|a|span)\b/i.test(content);
    const nbsp = /(?:&nbsp;|&#0*160;|&#x0*a0;|\u00a0)/gi;
    const formatted = content
        .replace(/([^\s])[ \t]+(\d+\.)(?=(?:&nbsp;|&#0*160;|&#x0*a0;|\u00a0){2,})/gi, isHtml ? '$1<br>$2' : '$1\n$2')
        .replace(nbsp, ' ');

    if (isHtml) {
        return DOMPurify.sanitize(formatted, {
            ...richTextOptions,
            ALLOWED_TAGS: [...richTextOptions.ALLOWED_TAGS, 'div', 'h1', 'h4', 'h5', 'h6', 'b', 'i'],
        });
    }

    return submissionContentToHtml(formatted);
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
