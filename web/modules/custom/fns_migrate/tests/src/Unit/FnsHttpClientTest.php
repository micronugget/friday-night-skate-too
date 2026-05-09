<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

/**
 * Unit tests for {@see FnsHttpClient}.
 *
 * Uses Guzzle's MockHandler so retry/backoff logic can be exercised without
 * the network. The cache layer is exercised against an in-memory FileSystem
 * stub keyed by the public:// scheme so the test never touches Drupal's
 * private:// stream wrapper.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Service\FnsHttpClient
 */
class FnsHttpClientTest extends UnitTestCase {

  /**
   * Captured outbound requests, in order.
   *
   * @var \Psr\Http\Message\RequestInterface[]
   */
  protected array $history = [];

  /**
   * Sleep durations recorded by the injected sleep callback.
   *
   * @var int[]
   */
  protected array $sleeps = [];

  /**
   * Temp directory used as the cache root for the in-memory FileSystem stub.
   */
  protected string $cacheDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->history = [];
    $this->sleeps = [];
    $this->cacheDir = sys_get_temp_dir() . '/fns_http_client_test_' . uniqid('', TRUE);
    mkdir($this->cacheDir, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->rrmdir($this->cacheDir);
    parent::tearDown();
  }

  /**
   * Build a FnsHttpClient backed by a Guzzle MockHandler.
   *
   * @param \GuzzleHttp\Psr7\Response[]|\Throwable[] $responses
   *   Queued responses returned in order by the mock handler.
   */
  protected function buildClient(array $responses): FnsHttpClient {
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($this->history));
    $guzzle = new Client(['handler' => $stack]);

    $service = new FnsHttpClient($guzzle, $this->createFileSystemStub(), new NullLogger());
    $service->setSleep(function (int $seconds): void {
      $this->sleeps[] = $seconds;
    });
    return $service;
  }

  /**
   * @covers ::fetch
   * @covers ::requestWithRetries
   */
  public function testSuccessfulRequestWritesCache(): void {
    $client = $this->buildClient([new Response(200, [], 'hello')]);
    $body = $client->fetch('https://example.test/a', 'routes', 'a');

    $this->assertSame('hello', $body);
    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame(FnsHttpClient::USER_AGENT, $request->getHeaderLine('User-Agent'));
    $this->assertFileExists($this->cacheDir . '/routes/a.html');
    $this->assertSame('hello', file_get_contents($this->cacheDir . '/routes/a.html'));
    $this->assertSame([], $this->sleeps, 'No throttling on the very first request.');
  }

  /**
   * @covers ::fetch
   */
  public function testCacheHitSkipsNetwork(): void {
    mkdir($this->cacheDir . '/routes', 0777, TRUE);
    file_put_contents($this->cacheDir . '/routes/a.html', 'cached body');

    // No mock responses queued: any HTTP call would explode the MockHandler.
    $client = $this->buildClient([]);
    $body = $client->fetch('https://example.test/a', 'routes', 'a');

    $this->assertSame('cached body', $body);
    $this->assertCount(0, $this->history);
  }

  /**
   * @covers ::requestWithRetries
   * @covers ::isRetryable
   * @covers ::backoffSeconds
   */
  public function testRetriesOn429ThenSucceeds(): void {
    $client = $this->buildClient([
      new Response(429, ['Retry-After' => '1'], 'slow down'),
      new Response(503, [], 'down'),
      new Response(200, [], 'ok'),
    ]);

    $body = $client->fetch('https://example.test/b', 'routes', 'b');

    $this->assertSame('ok', $body);
    $this->assertCount(3, $this->history);
    // Two backoff sleeps, exponentially increasing: 1s then 2s.
    $this->assertSame([1, 2], $this->sleeps);
  }

  /**
   * @covers ::requestWithRetries
   */
  public function testGivesUpOnNonRetryableStatus(): void {
    $client = $this->buildClient([new Response(404, [], 'gone')]);

    $this->expectException(\RuntimeException::class);
    $client->fetch('https://example.test/c', 'routes', 'c');
  }

  /**
   * @covers ::requestWithRetries
   */
  public function testExhaustsRetriesAndThrows(): void {
    $client = $this->buildClient([
      new Response(500),
      new Response(500),
      new Response(500),
      new Response(500),
      new Response(500),
    ]);

    $this->expectException(\RuntimeException::class);
    try {
      $client->fetch('https://example.test/d', 'routes', 'd');
    }
    finally {
      // 5 attempts (1 initial + 4 retries) means 4 backoff sleeps before
      // the final failure.
      $this->assertCount(5, $this->history);
      $this->assertSame([1, 2, 4, 8], $this->sleeps);
    }
  }

  /**
   * Build a minimal FileSystemInterface stub for the test cache root.
   *
   * Maps `private://fns_migrate_cache/...` paths onto a temporary directory
   * so cache reads/writes are testable in isolation.
   */
  protected function createFileSystemStub(): FileSystemInterface {
    $dir = $this->cacheDir;
    $stub = $this->createMock(FileSystemInterface::class);

    $resolve = static function (string $uri) use ($dir): string {
      // Strip "private://fns_migrate_cache/" → tmp/...
      $uri = preg_replace('#^private://fns_migrate_cache/?#', '', $uri);
      return rtrim($dir, '/') . '/' . ltrim((string) $uri, '/');
    };

    $stub->method('realpath')->willReturnCallback(static function (string $uri) use ($resolve) {
      $path = $resolve($uri);
      return file_exists($path) ? $path : FALSE;
    });

    $stub->method('prepareDirectory')->willReturnCallback(static function (string &$uri, int $options) use ($resolve): bool {
      $path = $resolve($uri);
      if (!is_dir($path)) {
        mkdir($path, 0777, TRUE);
      }
      return TRUE;
    });

    $stub->method('saveData')->willReturnCallback(static function (string $data, string $destination, int $replace) use ($resolve): string {
      $path = $resolve($destination);
      if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, TRUE);
      }
      file_put_contents($path, $data);
      return $destination;
    });

    return $stub;
  }

  /**
   * Recursively delete a directory.
   */
  protected function rrmdir(string $dir): void {
    if (!is_dir($dir)) {
      return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $path = $dir . '/' . $entry;
      is_dir($path) ? $this->rrmdir($path) : unlink($path);
    }
    rmdir($dir);
  }

}
