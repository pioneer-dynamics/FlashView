import { existsSync, unlinkSync } from 'fs';
import { resolve } from 'path';

export default async function globalSetup(): Promise<void> {
    const hotFile = resolve(process.cwd(), 'public/hot');
    if (existsSync(hotFile)) {
        unlinkSync(hotFile);
    }
}
