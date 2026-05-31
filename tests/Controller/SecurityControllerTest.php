<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Connexion');
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testSecurityHeadersArePresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $this->assertResponseHasHeader('X-Frame-Options');
        $this->assertSame('SAMEORIGIN', $client->getResponse()->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $client->getResponse()->headers->get('X-Content-Type-Options'));
    }
}
