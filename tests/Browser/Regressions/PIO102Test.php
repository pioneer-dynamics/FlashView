<?php

/**
 * @ticket PIO-102
 *
 * @symptom Uploading any file to eLocker fails with "Invalid array length" because
 *          encryptFileToBlob converted AES-GCM ciphertext to a hex string via
 *          Array.from().map().join(''), which exceeds V8's max string length for large files.
 *
 * Must fail before the fix (encryptFileToBlob hex path), pass after (encryptFileToBuffer).
 */
test('file upload does not produce Invalid array length error', function () {
    todo('Requires page.route() network interception for S3 presign/upload mocking — not available in pest-plugin-browser v4');
});

test('file locker creation completes and shows credentials panel', function () {
    todo('Requires page.route() network interception for S3 presign/upload mocking — not available in pest-plugin-browser v4');
});
