<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AppTestCase;

final class AuthenticationTest extends AppTestCase
{
    public function testValidCredentialsGrantAccess(): void
    {
        $app = $this->makeApp();

        $login = $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'secret']);
        self::assertSame(302, $login->getStatusCode());
        self::assertSame('/', $login->getHeaderLine('Location'));

        $library = $this->get($app, '/library/movies');
        self::assertSame(200, $library->getStatusCode());
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $app = $this->makeApp();

        $login = $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'wrong']);
        self::assertSame(401, $login->getStatusCode());

        $home = $this->get($app, '/');
        self::assertSame(302, $home->getStatusCode());
        self::assertSame('/login', $home->getHeaderLine('Location'));
    }

    public function testLogoutEndsSession(): void
    {
        $app = $this->makeApp();

        $this->postForm($app, '/login', ['username' => 'admin', 'password' => 'secret']);
        self::assertSame(200, $this->get($app, '/library/movies')->getStatusCode());

        $logout = $this->get($app, '/logout');
        self::assertSame(302, $logout->getStatusCode());
        self::assertSame('/login', $logout->getHeaderLine('Location'));

        self::assertSame(302, $this->get($app, '/')->getStatusCode());
    }

    public function testAuthBypassGrantsAccessWithoutLogin(): void
    {
        $response = $this->get($this->makeApp(['AUTH_BYPASS' => 'true']), '/library/movies');

        self::assertSame(200, $response->getStatusCode());
    }
}
