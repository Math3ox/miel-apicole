<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSecurityHeadersAreAddedOnMainRequest(): void
    {
        $response = $this->dispatch(HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function testHeadersAreNotAddedOnSubRequest(): void
    {
        $response = $this->dispatch(HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    private function dispatch(int $requestType): Response
    {
        $kernel   = $this->createStub(HttpKernelInterface::class);
        $response = new Response();
        $event    = new ResponseEvent($kernel, new Request(), $requestType, $response);

        (new SecurityHeadersSubscriber())->onKernelResponse($event);

        return $response;
    }
}
