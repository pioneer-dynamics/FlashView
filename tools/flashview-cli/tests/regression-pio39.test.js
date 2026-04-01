import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import Conf from 'conf';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cli = join(__dirname, '..', 'bin', 'flashview.js');

/**
 * PIO-39 Regression: --json suppresses version update notice
 *
 * The postAction hook was reading the --json option from `thisCommand`
 * (the root program) instead of `actionCommand` (the executed subcommand).
 * Since --json is defined on subcommands, the guard never triggered,
 * causing the version update notice to appear alongside JSON output.
 */
describe('PIO-39 Regression: --json suppresses version update notice', () => {
    const UPDATE_NOTICE_PATTERN = /Update available:.*Run.*flashview update/;

    const config = new Conf({ projectName: 'flashview-cli' });
    const savedKeys = [
        'latestVersion',
        'latestVersionCheckedAt',
        'serverConfig',
        'serverConfigFetchedAt',
    ];
    const originalValues = {};

    before(() => {
        for (const key of savedKeys) {
            originalValues[key] = config.get(key);
        }

        // Set a fake newer version so the update notice would trigger
        config.set('latestVersion', '99.0.0');
        config.set('latestVersionCheckedAt', Date.now());

        // Ensure server config is cached so the CLI doesn't try to fetch it
        config.set('serverConfig', {
            expiry_options: [{ label: '5 minutes', value: 5 }],
            max_expiry: 5,
            max_message_length: 10000,
        });
        config.set('serverConfigFetchedAt', Date.now());
    });

    after(() => {
        for (const key of savedKeys) {
            if (originalValues[key] !== undefined) {
                config.set(key, originalValues[key]);
            } else {
                config.delete(key);
            }
        }
    });

    /**
     * Runs the CLI with the given arguments and returns { stdout, stderr }.
     */
    function runCli(args) {
        const result = spawnSync(process.execPath, [cli, ...args], {
            cwd: join(__dirname, '..'),
            encoding: 'utf-8',
            timeout: 10000,
            stdio: ['pipe', 'pipe', 'pipe'],
        });
        return {
            stdout: result.stdout || '',
            stderr: result.stderr || '',
        };
    }

    it('suppresses the version update notice when --json is passed', () => {
        const result = runCli(['create', '--message', 'test', '--json']);

        assert.ok(
            !UPDATE_NOTICE_PATTERN.test(result.stderr),
            `Version notice should be suppressed with --json, but stderr contained:\n${result.stderr}`
        );
    });

    it('stdout does not contain the version update notice when --json is passed', () => {
        const result = runCli(['create', '--message', 'test', '--json']);

        assert.ok(
            !UPDATE_NOTICE_PATTERN.test(result.stdout),
            `Version notice should not appear in stdout with --json, but got:\n${result.stdout}`
        );
    });
});
