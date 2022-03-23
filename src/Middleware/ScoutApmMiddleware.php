<?php

declare(strict_types=1);

namespace Scoutapm\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Scoutapm\ScoutApmAgent;
use Throwable;

final class ScoutApmMiddleware implements MiddlewareInterface
{
    /** @var ScoutApmAgent */
    private $scoutApmAgent;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(ScoutApmAgent $scoutApmAgent, LoggerInterface $logger)
    {
        $this->scoutApmAgent = $scoutApmAgent;
        $this->logger        = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->scoutApmAgent->connect();

        try {
            $response = $this->scoutApmAgent->webTransaction(
                $request->getUri()->getPath(),
                static function () use ($request, $handler) {
                    return $handler->handle($request);
                }
            );
        } catch (Throwable $exception) {
            $this->scoutApmAgent->tagRequest('error', 'true');

            $this->scoutApmAgent->recordThrowable($exception);

            throw $exception;
        } finally {
            try {
                $this->scoutApmAgent->send();
            } catch (Throwable $e) {
                $this->logger->debug('PSR-15 Send to Scout failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return $response;
    }
}
