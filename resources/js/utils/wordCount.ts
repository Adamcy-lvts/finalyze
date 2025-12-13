export function countWords(text: string): number {
    if (!text) return 0;

    const cleanContent = String(text).replace(/<[^>]*>/g, '');
    const normalizedContent = cleanContent.trim().replace(/\s+/g, ' ');
    if (!normalizedContent) return 0;

    const matches = normalizedContent.match(/\b\w+\b/g);
    return matches ? matches.length : 0;
}

