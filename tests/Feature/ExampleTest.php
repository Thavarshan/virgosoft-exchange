<?php

test('the application boots', function () {
    $this->get('/up')->assertOk();
});
