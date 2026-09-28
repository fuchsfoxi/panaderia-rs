<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    // La raiz ya no es una pagina: sin sesion manda al login y con seson al
    // dashboard. Este test solo deja constancia de que la raiz responde.
    $response->assertStatus(302);
});
