// Browser-side polyfills for dependencies that assume Node globals.
// Keep this file side-effectful and imported before other JS modules.

declare global {
    interface Window {
        process?: { env?: Record<string, string>; browser?: boolean };
        global?: unknown;
    }
}

(() => {
    const g = globalThis as any;

    // Some dependencies (or their builds) still reference `process` / `process.env`.
    if (!g.process) {
        g.process = { env: {}, browser: true };
    } else {
        g.process.env = g.process.env ?? {};
        g.process.browser = g.process.browser ?? true;
    }

    // Provide NODE_ENV when possible (commonly checked for dev/prod branches).
    try {
        if (!g.process.env.NODE_ENV) {
            g.process.env.NODE_ENV = import.meta.env.MODE;
        }
    } catch {
        // Ignore if `import.meta.env` is not available.
    }

    // Some libraries also expect a `global` alias.
    if (!g.global) {
        g.global = g;
    }
})();

