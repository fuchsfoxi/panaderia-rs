<?php

test('la raíz redirige al login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
