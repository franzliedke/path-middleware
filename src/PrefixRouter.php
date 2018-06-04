<?php
declare(strict_types = 1);

namespace Middlewares;

use Middlewares\PathUtil\PrefixingHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class PrefixRouter implements Middleware
{
    /** @var array */
    private $middlewares;

    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;

        // Make sure the longest path prefixes are matched first
        // (otherwise, a path /foo would always match, even when /foo/bar
        // should match).
        krsort($this->middlewares);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $requestPath = $this->getNormalizedPath($request);

        foreach ($this->middlewares as $pathPrefix => $middleware) {
            if (strpos($requestPath, $pathPrefix) === 0) {
                return $middleware->process(
                    $this->unprefixedRequest($request, $pathPrefix),
                    $this->prefixedHandler($handler, $pathPrefix)
                );
            }
        }

        return $handler->handle($request);
    }

    private function unprefixedRequest(Request $request, string $prefix): Request
    {
        $uri = $request->getUri();
        return $request->withUri(
            $uri->withPath(
                substr($uri->getPath(), strlen($prefix))
            )
        );
    }

    private function prefixedHandler(Handler $handler, string $prefix): Handler
    {
        return new PrefixingHandler($handler, $prefix);
    }

    private function getNormalizedPath(Request $request): string
    {
        $path = $request->getUri()->getPath();
        if (empty($path)) {
            $path = '/';
        }

        return $path;
    }
}
