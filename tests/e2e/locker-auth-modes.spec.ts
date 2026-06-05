import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import {
    createLockerCredit,
    createLockerViaUI,
    createKeyFileLockerViaUI,
    createCombinedLockerViaUI,
    KEY_FILE_ALPHA,
    KEY_FILE_BETA,
    KEY_FILE_WRONG,
} from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

// ─── Scenario 1: Passphrase-only mode (regression) ────────────────────────────

test('passphrase-only creation and unlock still works (regression)', async ({ page }) => {
    createLockerCredit('regtoken01', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '8000000001', 'regression-passphrase-ok', 'Regression content', 'regtoken01');

    await page.goto('/lockers/8000000001');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Regression content')).toBeVisible();
});

// ─── Scenario 2: Key file creation ────────────────────────────────────────────

test('key file mode creation shows filename and credential panel without passphrase', async ({ page }) => {
    createLockerCredit('kfcreate01', 'text', 1);

    await page.goto('/lockers/create?token=kfcreate01');
    await page.waitForLoadState('networkidle');

    // Select key file mode
    await page.getByRole('button', { name: 'Key File(s)' }).click();
    await expect(page.getByText(/rotation is not yet supported/i)).toBeVisible();

    // Passphrase field should be hidden
    await expect(page.getByPlaceholder('Enter or generate a passphrase')).not.toBeVisible();

    // Add a key file — filename should appear (no fingerprint)
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    await expect(page.getByText(KEY_FILE_ALPHA.name)).toBeVisible();

    // Risk acknowledgement checkbox must be checked before submit
    await page.getByTestId('key-file-risk-checkbox').check();
    await page.getByPlaceholder('Enter the content to store…').fill('Key file protected content');
    await page.getByPlaceholder('Choose a 10-digit number').fill('8000000002');
    await page.getByTestId('create-submit-button').click();

    await expect(page.getByText('Locker created!')).toBeVisible({ timeout: 15000 });
    // Passphrase field not shown in credentials panel (key_file mode)
    await expect(page.getByText('Passphrase', { exact: true })).not.toBeVisible();
    // Key file names shown in credentials panel (no fingerprints)
    await expect(page.getByText('Key Files (load in this order)')).toBeVisible();
    await expect(page.getByText(KEY_FILE_ALPHA.name)).toBeVisible();
});

test('key file mode creation blocked if risk checkbox is not checked', async ({ page }) => {
    createLockerCredit('kfcreate02', 'text', 1);

    await page.goto('/lockers/create?token=kfcreate02');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Key File(s)' }).click();
    await page.getByPlaceholder('Choose a 10-digit number').fill('8000000003');
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    await page.getByPlaceholder('Enter the content to store…').fill('Content');
    // Do NOT check the risk checkbox
    await page.getByTestId('create-submit-button').click();

    await expect(page.getByText('acknowledge the key file risk')).toBeVisible();
    await expect(page.getByText('Locker created!')).not.toBeVisible();
});

// ─── Scenario 3: Key file unlock success ─────────────────────────────────────

test('key file mode unlock succeeds with correct key file', async ({ page }) => {
    createLockerCredit('kfunlock01', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000010', 'Unlockable key file content', 'kfunlock01', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000010');
    await page.waitForLoadState('networkidle');

    // Passphrase input not shown for key_file mode
    await expect(page.getByTestId('passphrase-input')).not.toBeVisible();

    // Key file picker guidance visible
    await expect(page.getByText(/same order as when you created/i)).toBeVisible();

    // Load the correct key file
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);

    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Unlockable key file content')).toBeVisible();
});

// ─── Scenario 4: Key file unlock fails with wrong file ────────────────────────

test('key file mode unlock fails with wrong key file', async ({ page }) => {
    createLockerCredit('kfunlock02', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000011', 'Content protected by correct file', 'kfunlock02', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000011');
    await page.waitForLoadState('networkidle');

    // Load the WRONG key file
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_WRONG);

    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypt-error')).toBeVisible({ timeout: 12000 });
    await expect(page.getByText(/Credentials do not match|Incorrect credentials|key file order/i)).toBeVisible();
    // Content must NOT be revealed
    await expect(page.getByTestId('decrypted-content')).not.toBeVisible();
});

// ─── Scenario 5: Combined mode creation ──────────────────────────────────────

test('combined mode creation shows both passphrase and key file section', async ({ page }) => {
    createLockerCredit('cmb01', 'text', 1);

    await page.goto('/lockers/create?token=cmb01');
    await page.waitForLoadState('networkidle');

    // Select combined mode
    await page.getByRole('button', { name: 'Both' }).click();

    // Both passphrase and key file section should be visible
    await expect(page.getByPlaceholder('Enter or generate a passphrase')).toBeVisible();
    await expect(page.getByTestId('key-file-input')).toBeAttached();
    await expect(page.getByText(/both passphrase and all/i)).toBeVisible();
});

test('combined mode locker creation completes successfully', async ({ page }) => {
    createLockerCredit('cmb02', 'text', 1);
    await createCombinedLockerViaUI(page, '8000000020', 'combined-pass-long', 'Combined mode content', 'cmb02', [KEY_FILE_ALPHA]);

    await expect(page.getByText('Locker created!')).toBeVisible();
    // Both passphrase and key file names shown in credentials
    await expect(page.getByText('Passphrase', { exact: true })).toBeVisible();
    await expect(page.getByText('Key Files (load in this order)')).toBeVisible();
    await expect(page.getByText(KEY_FILE_ALPHA.name)).toBeVisible();
});

// ─── Scenario 6: Combined mode unlock success ────────────────────────────────

test('combined mode unlock succeeds with both passphrase and correct key file', async ({ page }) => {
    createLockerCredit('cmb03', 'text', 1);
    await createCombinedLockerViaUI(page, '8000000021', 'combined-unlock-pass', 'Combined unlock content', 'cmb03', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000021');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill('combined-unlock-pass');
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);

    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Combined unlock content')).toBeVisible();
});

// ─── Scenario 7: Combined mode fails with passphrase only ────────────────────

test('combined mode unlock fails when passphrase provided but no key file', async ({ page }) => {
    createLockerCredit('cmb04', 'text', 1);
    await createCombinedLockerViaUI(page, '8000000022', 'combined-passonly-pass', 'Content', 'cmb04', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000022');
    await page.waitForLoadState('networkidle');

    // Provide passphrase but NO key file — unlock button must remain disabled
    await page.getByTestId('passphrase-input').fill('combined-passonly-pass');

    await expect(page.getByTestId('unlock-button')).toBeDisabled();
});

// ─── Scenario 8: Combined mode fails with file only ──────────────────────────

test('combined mode unlock fails when key file provided but no passphrase', async ({ page }) => {
    createLockerCredit('cmb05', 'text', 1);
    await createCombinedLockerViaUI(page, '8000000023', 'combined-fileonly-pass', 'Content', 'cmb05', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000023');
    await page.waitForLoadState('networkidle');

    // Load key file but NO passphrase — unlock button must remain disabled
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);

    // Passphrase is empty → canUnlock should be false
    await expect(page.getByTestId('unlock-button')).toBeDisabled();
});

// ─── Scenario 9: Multiple key files ──────────────────────────────────────────

test('multiple key files: creation and successful unlock with all files', async ({ page }) => {
    createLockerCredit('mkf01', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000030', 'Two key file content', 'mkf01', [KEY_FILE_ALPHA, KEY_FILE_BETA]);

    await page.goto('/lockers/8000000030');
    await page.waitForLoadState('networkidle');

    // Unlock button disabled until both files loaded
    await expect(page.getByTestId('unlock-button')).toBeDisabled();

    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    // Still disabled — only 1 of 2 loaded
    await expect(page.getByTestId('unlock-button')).toBeDisabled();

    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_BETA);
    // Now enabled
    await expect(page.getByTestId('unlock-button')).toBeEnabled();

    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Two key file content')).toBeVisible();
});

// ─── Scenario 10: Multiple key files — only 1 of 2 provided ─────────────────

test('multiple key files: unlock button disabled when only 1 of 2 files loaded', async ({ page }) => {
    createLockerCredit('mkf02', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000031', 'Two key file content', 'mkf02', [KEY_FILE_ALPHA, KEY_FILE_BETA]);

    await page.goto('/lockers/8000000031');
    await page.waitForLoadState('networkidle');

    // Load only 1 of 2 key files
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);

    // Progress indicator shows 1 / 2
    await expect(page.getByText(/1 \/ 2/)).toBeVisible();

    // Unlock button still disabled
    await expect(page.getByTestId('unlock-button')).toBeDisabled();
});

// ─── Scenario 11: Credential download includes key file names ────────────────

test('credential panel shows key file names (not fingerprints) and download button is visible', async ({ page }) => {
    createLockerCredit('credkf01', 'text', 1);

    await page.goto('/lockers/create?token=credkf01');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Key File(s)' }).click();
    await page.getByPlaceholder('Choose a 10-digit number').fill('8000000040');
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    await page.getByTestId('key-file-risk-checkbox').check();
    await page.getByPlaceholder('Enter the content to store…').fill('Credentials test content');
    await page.getByTestId('create-submit-button').click();

    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    // Key file names shown (no fingerprints)
    await expect(page.getByText('Key Files (load in this order)')).toBeVisible();
    await expect(page.getByText(KEY_FILE_ALPHA.name)).toBeVisible();
    await expect(page.getByText('loaded in this exact order')).toBeVisible();

    // Download button present
    await expect(page.getByRole('button', { name: /Download as text file/i })).toBeVisible();
});

// ─── Additional: auth mode UI interaction ────────────────────────────────────

test('switching back to passphrase mode hides key file section and clears added files', async ({ page }) => {
    createLockerCredit('modeswitch01', 'text', 1);

    await page.goto('/lockers/create?token=modeswitch01');
    await page.waitForLoadState('networkidle');

    // Go to key_file mode and add a file
    await page.getByRole('button', { name: 'Key File(s)' }).click();
    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    await expect(page.getByText(KEY_FILE_ALPHA.name)).toBeVisible();

    // Switch back to passphrase — key file list and section should be gone
    await page.getByRole('button', { name: 'Passphrase' }).click();
    await expect(page.getByPlaceholder('Enter or generate a passphrase')).toBeVisible();
    await expect(page.getByTestId('key-file-input')).not.toBeVisible();
});

test('show page for key file locker does not show passphrase input', async ({ page }) => {
    createLockerCredit('kfshow01', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000050', 'Key file show test', 'kfshow01', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000050');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('passphrase-input')).not.toBeVisible();
    await expect(page.getByTestId('key-file-input')).toBeAttached();
    await expect(page.getByText(/same order as when you created/i)).toBeVisible();
});

test('change passphrase panel hidden for key file mode; change credentials panel shown instead', async ({ page }) => {
    createLockerCredit('kfcp01', 'text', 1);
    await createKeyFileLockerViaUI(page, '8000000051', 'Key file no passchange', 'kfcp01', [KEY_FILE_ALPHA]);

    await page.goto('/lockers/8000000051');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('key-file-input').setInputFiles(KEY_FILE_ALPHA);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    // Passphrase change panel must NOT be visible for key file lockers
    await expect(page.getByRole('heading', { name: /Change Passphrase/i })).not.toBeVisible();
    // Change Credentials panel must be visible instead
    await expect(page.getByRole('heading', { name: /Change Credentials/i })).toBeVisible();
    await expect(page.getByTestId('rotate-credentials-button')).toBeVisible();
});
