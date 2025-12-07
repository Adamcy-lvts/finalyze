import createDOMPurify from 'dompurify';

let purifier: ReturnType<typeof createDOMPurify> | null = null;

const ALLOWED_TAGS = [
    'b', 'strong', 'i', 'em', 'u', 'span', 'p', 'br', 'sub', 'sup', 'mark',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'div', 'blockquote', 'pre', 'code'
];

const stripDangerousContent = (value: string) =>
    value
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/on\w+="[^"]*"/gi, '')
        .replace(/on\w+='[^']*'/gi, '');

const getPurifier = () => {
    if (purifier) return purifier;
    if (typeof window === 'undefined') {
        return null;
    }

    purifier = createDOMPurify(window);
    return purifier;
};

export const sanitizeHtmlContent = (value?: string | null) => {
    if (!value) return '';

    const instance = getPurifier();
    if (!instance) {
        return stripDangerousContent(value);
    }

    return instance.sanitize(value, { ALLOWED_TAGS });
};

export const hasHtmlContent = (value?: string | null) => {
    if (!value) return false;
    return /<\/?[a-z][\s\S]*>/i.test(value);
};
