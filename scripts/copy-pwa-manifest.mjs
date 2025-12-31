import { promises as fs } from 'node:fs';
import path from 'node:path';

const sourcePath = path.resolve('public/build/manifest.webmanifest');
const targetPath = path.resolve('public/manifest.webmanifest');

const ensureManifestCopy = async () => {
    try {
        const manifest = await fs.readFile(sourcePath);
        await fs.writeFile(targetPath, manifest);
    } catch (error) {
        console.warn('PWA: Unable to copy manifest.webmanifest', error);
    }
};

await ensureManifestCopy();
