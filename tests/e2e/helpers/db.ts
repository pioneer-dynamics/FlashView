import { execSync } from 'child_process';

export function resetDatabase(): void {
    // Do NOT pass --seed — seeder may create data that conflicts with test assertions.
    execSync('php artisan migrate:fresh --env=testing --no-interaction', {
        stdio: 'pipe',
    });
}

export function expireAllSecrets(): void {
    // Replicates what ClearExpiredSecrets job does for text secrets.
    // There is no artisan command for this — use tinker directly.
    execSync(
        'php artisan tinker --no-interaction --env=testing --execute="App\\\\Models\\\\Secret::query()->update([\'expires_at\' => now()->subMinute(), \'message\' => null])"',
        { stdio: 'pipe' }
    );
}
