<?php

test('plans page renders for guest', function () {
    seedPlans();

    $page = visit('/plans');

    $page->assertVisible('h1, h2');
    $page->assertVisible('h5');
});

test('plans page renders for authenticated user', function () {
    seedPlans();

    $user = createTestUser();
    $page = browserLogin($user)
        ->navigate('/plans');

    $page->assertVisible('h1, h2');
    $page->assertVisible('h5');
});
