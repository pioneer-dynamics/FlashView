import { execSync } from 'child_process';

const ARTISAN = process.env.CI ? 'php artisan' : 'vendor/bin/sail artisan';

/**
 * Create an active call session via tinker and return its hash_id.
 */
export function createActiveCallSession(): string {
    const output = execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="echo json_encode(['hash_id' => App\\\\Models\\\\CallSession::factory()->create()->hash_id]);"`,
        { stdio: 'pipe' }
    ).toString().trim();
    const match = output.match(/\{"hash_id":"([^"]+)"\}/);
    if (!match) throw new Error(`Could not extract hash_id from tinker output: ${output}`);
    return match[1];
}

/**
 * Create a call session that starts in the future via tinker and return its hash_id.
 */
export function createFutureCallSession(): string {
    const output = execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="echo json_encode(['hash_id' => App\\\\Models\\\\CallSession::factory()->notYetStarted()->create()->hash_id]);"`,
        { stdio: 'pipe' }
    ).toString().trim();
    const match = output.match(/\{"hash_id":"([^"]+)"\}/);
    if (!match) throw new Error(`Could not extract hash_id from tinker output: ${output}`);
    return match[1];
}
