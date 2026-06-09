export function sanitizeHtml(html: string): string {
    return html
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/\son\w+\s*=\s*(".*?"|'.*?'|[^\s>]+)/gi, '')
        .replace(/\s(href|src)\s*=\s*("|\')?\s*javascript:[^"'>\s]*(\2)?/gi, '')
}
