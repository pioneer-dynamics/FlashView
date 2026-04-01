import { execSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const SENTINEL_FUSE = 'NODE_SEA_FUSE_fce680ab2cc467b6e072b8b5df1996b2';

function run(cmd) {
    console.log(`> ${cmd}`);
    execSync(cmd, { stdio: 'inherit' });
}

function getPlatformInfo() {
    const os = process.platform;
    const arch = process.arch;
    const osName = os === 'win32' ? 'windows' : os;
    const ext = os === 'win32' ? '.exe' : '';
    return { os, osName, arch, ext };
}

const { os, osName, arch, ext } = getPlatformInfo();
const binaryName = `flashview-${osName}-${arch}${ext}`;

if (!existsSync('dist')) mkdirSync('dist');

// Step 1: Generate SEA blob
console.log('Building SEA blob...');
run('node --experimental-sea-config sea-config.json');

// Step 2: Copy Node.js binary
const nodePath = process.execPath;
const outputPath = join('dist', binaryName);
console.log(`Copying Node.js binary to ${outputPath}...`);
copyFileSync(nodePath, outputPath);

// Step 3: Platform-specific blob injection
const postjectBase = `npx postject "${outputPath}" NODE_SEA_BLOB dist/sea-prep.blob --sentinel-fuse ${SENTINEL_FUSE}`;

if (os === 'darwin') {
    run(`codesign --remove-signature "${outputPath}"`);
    run(`${postjectBase} --macho-segment-name NODE_SEA`);
    run(`codesign --sign - "${outputPath}"`);
} else if (os === 'win32') {
    run(`${postjectBase} --overwrite`);
} else {
    run(postjectBase);
}

console.log(`\nBinary built: ${outputPath}`);
