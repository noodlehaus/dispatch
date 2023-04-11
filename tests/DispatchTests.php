<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../dispatch.php';

class DispatchTests extends TestCase {

  private function dispatchWithRequest(
    string $requestMethod,
    string $requestUri
  ): string {
    $_SERVER['REQUEST_METHOD'] = $requestMethod;
    $_SERVER['REQUEST_URI'] = $requestUri;
    ob_start();
    dispatch();
    return ob_get_clean();
  }

  public function testStash(): void {
    stash('test_key', 'test_value');
    $this->assertSame('test_value', stash('test_key'), 'stash: setting and getting a value failed');
  }

  /**
   * @runInSeparateProcess
   */
  public function testDispatchRouteResponse404(): void {

    route('GET', '/test', fn() => response('Hello, World!'));
    route('GET', '/test-404', fn() => _404()());

    $output = $this->dispatchWithRequest('GET', '/test');
    $this->assertSame('Hello, World!', $output, 'dispatch: failed to dispatch the correct route');
  }

  /**
   * @runInSeparateProcess
   */
  public function testApplyMiddleware(): void {

    $middlewareCalled = false;

    $middleware = function ($next) use (&$middlewareCalled) {
      $middlewareCalled = true;
      return $next();
    };

    apply('/test-middleware', $middleware);
    route('GET', '/test-middleware', fn() => response('Middleware Applied!'));

    $output = $this->dispatchWithRequest('GET', '/test-middleware');
    $this->assertSame('Middleware Applied!', $output, 'dispatch: failed to dispatch the route with middleware');
    $this->assertTrue($middlewareCalled, 'apply: middleware was not called');
  }

  /**
   * @runInSeparateProcess
   */
  public function testBind(): void {

    bind('id', fn($value) => intval($value));
    route('GET', '/bind/:id', fn($params) => response('Bound value: ' . $params['id']));

    $output = $this->dispatchWithRequest('GET', '/bind/42');
    $this->assertSame('Bound value: 42', $output, 'bind: failed to bind and transform the parameter');
  }

  /**
   * @runInSeparateProcess
   */
  public function testRedirect(): void {

    route('GET', '/redirect', fn() => redirect('/test-redirect'));

    $output = $this->dispatchWithRequest('GET', '/redirect');
    $this->assertTrue(empty($output) && http_response_code() === 302, 'redirect: failed to create a redirect response');
  }

  /**
   * @runInSeparateProcess
   */
  public function testPhtml(): void {
    file_put_contents('test_template.phtml', 'Hello, <?= $name ?>!');
    $output = phtml('test_template', ['name' => 'World']);
    $this->assertSame('Hello, World!', $output, 'phtml: failed to render and return the content of a template');
    unlink('test_template.phtml');
  }

  /**
   * @runInSeparateProcess
   */
  public function test404(): void {

    $custom404Called = false;

    _404(function () use (&$custom404Called) {
      $custom404Called = true;
      return response('Custom 404', 404);
    });

    $output = $this->dispatchWithRequest('GET', '/non-existent-route');
    $this->assertSame('Custom 404', $output, 'dispatch: failed to dispatch the custom 404 route');
    $this->assertTrue($custom404Called, '_404: custom 404 handler was not called');
  }

  /**
   * @runInSeparateProcess
   */
  public function testPostMethodOverride(): void {
    route('PUT', '/method-override', fn() => response('Method override!'));
    $_POST['_method'] = 'PUT';
    $output = $this->dispatchWithRequest('POST', '/method-override');
    $this->assertSame('Method override!', $output, 'dispatch: failed to handle method override with POST data');
  }

  /**
   * @runInSeparateProcess
   */
  public function testPostMethodOverrideViaHeader(): void {
    route('PUT', '/method-override', fn() => response('Method override!'));
    $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';
    $output = $this->dispatchWithRequest('POST', '/method-override');
    $this->assertSame('Method override!', $output, 'dispatch: failed to handle method override with POST data');
  }
}
