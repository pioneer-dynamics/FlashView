import { execSync } from 'child_process';

const ARTISAN = process.env.CI ? 'php artisan' : 'vendor/bin/sail artisan';

export function resetDatabase(): void {
    // Do NOT pass --seed — seeder may create data that conflicts with test assertions.
    execSync(`${ARTISAN} migrate:fresh --env=testing --no-interaction`, {
        stdio: 'pipe',
    });
}

export function seedPlans(): void {
    execSync(`${ARTISAN} db:seed --class=PlanSeederLocal --env=testing --no-interaction`, {
        stdio: 'pipe',
    });
}

export function clearCache(): void {
    execSync(`${ARTISAN} cache:clear --env=testing --no-interaction`, { stdio: 'pipe' });
}

export function expireAllSecrets(): void {
    // Replicates what ClearExpiredSecrets job does for text secrets.
    // There is no artisan command for this — use tinker directly.
    execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="App\\\\Models\\\\Secret::query()->update(['expires_at' => now()->subMinute(), 'message' => null])"`,
        { stdio: 'pipe' }
    );
}
