<?php

// The portal home sends guests to the login page.
it('sends guests from the home page to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
