<?php

declare(strict_types=1);

it('returns a successful response from the welcome route', function (): void {
    $this->get('/')->assertStatus(200);
});
