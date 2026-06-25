<?php

test('web responses include x frame options deny', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'DENY');
});

test('web responses include csp frame ancestors', function () {
    $response = $this->get('/');

    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});
