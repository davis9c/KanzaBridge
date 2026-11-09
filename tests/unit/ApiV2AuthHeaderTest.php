<?php

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ApiV2AuthHeaderTest extends CIUnitTestCase
{
    public function testBearerTokenFromAuthorizationHeaderIsAccepted(): void
    {
        $controller = new class extends \App\Controllers\ApiV2\BaseController {
            public function __construct()
            {
            }

            public function setRequestForTest(IncomingRequest $request): void
            {
                $this->request = $request;
            }
        };

        $request = new IncomingRequest(new \Config\App(), new URI('http://localhost'), null, new UserAgent());
        $request->setHeader('Authorization', 'Bearer test-token');
        $controller->setRequestForTest($request);

        $this->assertSame('test-token', $this->invokeMethod($controller, 'getBearerToken'));
    }

    public function testBearerTokenFromRedirectHttpAuthorizationHeaderIsAccepted(): void
    {
        $controller = new class extends \App\Controllers\ApiV2\BaseController {
            public function __construct()
            {
            }

            public function setRequestForTest(IncomingRequest $request): void
            {
                $this->request = $request;
            }
        };

        $request = new IncomingRequest(new \Config\App(), new URI('http://localhost'), null, new UserAgent());
        $request->setHeader('Authorization', 'Bearer redirected-token');
        $controller->setRequestForTest($request);

        $this->assertSame('redirected-token', $this->invokeMethod($controller, 'getBearerToken'));
    }

    private function invokeMethod(object $object, string $methodName)
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($object);
    }
}
