<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.5.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Http\Middleware;

use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * Test for CsrfProtection
 */
class CsrfProtectionMiddlewareTest extends TestCase
{

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * teardown
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Data provider for HTTP method tests.
     *
     * HEAD and GET do not populate $_POST or request->data.
     *
     * @return array
     */
    public static function safeHttpMethodProvider()
    {
        return [
            ['GET'],
            ['HEAD'],
        ];
    }

    /**
     * Data provider for HTTP methods that can contain request bodies.
     *
     * @return array
     */
    public static function httpMethodProvider()
    {
        return [
            ['OPTIONS'], ['PATCH'], ['PUT'], ['POST'], ['DELETE'], ['PURGE'], ['INVALIDMETHOD']
        ];
    }

    /**
     * Provides the callback for the next middleware
     *
     * @return callable
     */
    protected function _getNextClosure() {
        return function ($request, $response) {
            return $response;
        };
    }

    /**
     * Test setting the cookie value
     *
     * @return void
     */
    public function testSettingCookie()
    {
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'webroot' => '/dir/',
        ]);
        $response = new Response();

        $middleware = new CsrfProtectionMiddleware();
        $response = $middleware($request, $response, $this->_getNextClosure());
        $cookie = $response->cookie('csrfToken');

        $this->assertNotEmpty($cookie, 'Should set a token.');
        $this->assertRegExp('/^[a-f0-9]+$/', $cookie['value'], 'Should look like a hash.');
        $this->assertEquals(0, $cookie['expire'], 'session duration.');
        $this->assertEquals('/dir/', $cookie['path'], 'session path.');
        $this->assertEquals($cookie['value'], $request->params['_csrfToken']);
    }

    /**
     * Test that the CSRF tokens are not required for idempotent operations
     *
     * @dataProvider safeHttpMethodProvider
     * @return void
     */
    public function testSafeMethodNoCsrfRequired($method)
    {
        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => $method,
                'HTTP_X_CSRF_TOKEN' => 'nope',
            ],
            'cookies' => ['csrfToken' => 'testing123']
        ]);
        $response = new Response();

        // No exception means the test is valid
        $middleware = new CsrfProtectionMiddleware();
        $middleware($request, $response, $this->_getNextClosure());
    }

    /**
     * Test that the X-CSRF-Token works with the various http methods.
     *
     * @dataProvider httpMethodProvider
     * @return void
     */
    public function testValidTokenInHeader($method)
    {
        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => $method,
                'HTTP_X_CSRF_TOKEN' => 'testing123',
            ],
            'post' => ['a' => 'b'],
            'cookies' => ['csrfToken' => 'testing123']
        ]);
        $response = new Response();

        // No exception means the test is valid
        $middleware = new CsrfProtectionMiddleware();
        $middleware($request, $response, $this->_getNextClosure());
    }

    /**
     * Test that the X-CSRF-Token works with the various http methods.
     *
     * @dataProvider httpMethodProvider
     * @expectedException \Cake\Network\Exception\InvalidCsrfTokenException
     * @return void
     */
    public function testInvalidTokenInHeader($method)
    {
        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => $method,
                'HTTP_X_CSRF_TOKEN' => 'nope',
            ],
            'post' => ['a' => 'b'],
            'cookies' => ['csrfToken' => 'testing123']
        ]);
        $response = new Response();

        $middleware = new CsrfProtectionMiddleware();
        $middleware($request, $response, $this->_getNextClosure());
    }
}