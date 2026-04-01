import { build } from 'esbuild';
import { readFileSync } from 'node:fs';

const pkg = JSON.parse(readFileSync('package.json', 'utf8'));

await build({
    entryPoints: ['bin/flashview.js'],
    bundle: true,
    platform: 'node',
    target: 'node20',
    format: 'cjs',
    outfile: 'dist/flashview.cjs',
    external: [],
    minify: false,
    sourcemap: false,
    define: {
        '__VERSION__': JSON.stringify(pkg.version),
    },
    banner: {
        js: '// FlashView CLI - Bundled for SEA',
    },
});

console.log('Bundle created: dist/flashview.cjs');
