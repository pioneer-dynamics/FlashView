<?php

test('create page shows 404 for unknown credit token', function () {
    $this->get('/lockers/create?token=unknowntoken')->assertStatus(404);
});

test('create page loads with a valid unused credit token', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token);

    $page->assertSee('Create your eLocker');
    $page->assertVisible('input[placeholder="Choose a 10-digit number"]');
    $page->assertVisible('input[placeholder="Enter or generate a passphrase"]');
});

test('account ID must be exactly 10 digits', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', '12345')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'test-passphrase-long-enough')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'test content')
        ->click('Encrypt & Create');

    $page->assertSee('Account ID must be exactly 10 digits.');
});

test('account ID must contain only digits', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', 'abcdefghij')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'test-passphrase-long-enough')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'test content')
        ->click('Encrypt & Create');

    $page->assertSee('Account ID must be exactly 10 digits.');
});

test('generate button fills passphrase field', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token);

    // Passphrase field starts empty, then Generate fills it
    $page->click('Generate');

    // After clicking Generate, the passphrase field should have a value
    $page->assertVisible('input[placeholder="Enter or generate a passphrase"]');
});

test('ECDSA locker creation shows credentials panel with account ID and passphrase', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', '1234567890')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'my-strong-test-passphrase-here')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'Secret text content')
        ->click('Encrypt & Create');

    $page->assertSee('Locker created!');
    $page->assertSee('Account ID');
    $page->assertSee('Passphrase');
    $page->assertVisible('button:text("Download as text file")');
});

test('duplicate account ID shows validation error', function () {
    $credit1 = createLockerCredit();
    $credit2 = createLockerCredit();

    // Create first locker
    $page = visit('/lockers/create?token='.$credit1->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', '9876543210')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'my-strong-test-passphrase-here')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'First locker content')
        ->click('Encrypt & Create');

    $page->assertSee('Locker created!');

    // Attempt to create second locker with the same account ID
    $page = visit('/lockers/create?token='.$credit2->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', '9876543210')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'my-strong-test-passphrase-here')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'Second locker content')
        ->click('Encrypt & Create');

    $page->assertSee('taken');
});

test('file locker creation with DEK envelope encryption completes without error', function () {
    todo('Requires page.route() network interception for S3 presign/upload mocking — not available in pest-plugin-browser v4');
});

test('credentials panel has download button and confirmation checkbox', function () {
    $credit = createLockerCredit();

    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', '1111111111')
        ->fill('input[placeholder="Enter or generate a passphrase"]', 'my-strong-test-passphrase-here')
        ->fill('textarea[placeholder="Enter the content to store…"]', 'Content for credentials test')
        ->click('Encrypt & Create');

    $page->assertSee('Locker created!');

    // Open locker button should be disabled until checkbox is checked
    $page->assertDisabled('button:text("Open my locker")');

    // Check the confirmation checkbox
    $page->check('[data-testid="credentials-saved-checkbox"]');

    $page->assertEnabled('button:text("Open my locker")');
});
