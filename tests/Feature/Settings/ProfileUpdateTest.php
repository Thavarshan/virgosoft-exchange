<?php

test('the wallet route is available as a frontend shell', function () {
    $this->get('/wallet')->assertOk();
});
