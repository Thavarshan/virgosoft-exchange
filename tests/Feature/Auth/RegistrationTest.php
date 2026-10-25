<?php

test('public registration is disabled', function () {
    $this->postJson('/register', [
        'name' => 'New User', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertMethodNotAllowed();
});
