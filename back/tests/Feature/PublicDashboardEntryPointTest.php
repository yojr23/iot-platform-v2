<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicDashboardEntryPointTest extends TestCase
{
    public function test_backend_dashboard_redirects_to_canonical_spa(): void
    {
        config(['app.front_url' => 'http://frontend.test']);

        $response = $this->get('/dashboard');

        $response->assertRedirect('http://frontend.test/dashboard');
    }

    public function test_backend_root_redirects_to_canonical_spa(): void
    {
        config(['app.front_url' => 'http://frontend.test']);

        $response = $this->get('/');

        $response->assertRedirect('http://frontend.test/dashboard');
    }
}
