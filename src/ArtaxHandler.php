<?php
namespace ArtaxGuzzleBridge;

use Amp\Artax\HttpClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ArtaxHandler
{
    private $artaxClient;

    public function __construct(HttpClient $artaxClient)
    {
        $this->artaxClient = $artaxClient;
    }

    public function __invoke(RequestInterface $request, array $options)
    {
        $artaxRequest = $this->convertRequest($request, $options);
        $artaxResponsePromise = $this->artaxClient->request($artaxRequest);

        $guzzleResponsePromise = new Promise(function () use ($artaxResponsePromise) {
            \Amp\wait($artaxResponsePromise);
        });

        $artaxResponsePromise->when(
            function ($error = null, Response $artaxResponse = null) use ($request, $options, $guzzleResponsePromise) {
                if ($error) {
                    $guzzleResponsePromise->reject($error);
                } else {
                    $response = $this->convertResponse($artaxResponse, $request, $options);
                    $guzzleResponsePromise->resolve($response);
                }
            }
        );

        return $guzzleResponsePromise;
    }

    /**
     * @return Request
     */
    protected function convertRequest(RequestInterface $request, array $options)
    {
        $artaxRequest = new Request;

        $artaxRequest->setProtocol($request->getProtocolVersion());
        $artaxRequest->setMethod($request->getMethod());
        $artaxRequest->setUri((string)$request->getUri());
        $artaxRequest->setAllHeaders($request->getHeaders());

        $body = $request->getBody();
        if ($body->getSize() === null || $body->getSize() > 0) {
            $body->rewind();
            $artaxRequest->setBody(new PsrStreamIterator($body));
        }

        return $artaxRequest;
    }

    /**
     * @return ResponseInterface
     */
    protected function convertResponse(Response $artaxResponse, RequestInterface $request, array $requestOptions)
    {
        return new \GuzzleHttp\Psr7\Response(
            $artaxResponse->getStatus(),
            $artaxResponse->getAllHeaders(),
            $artaxResponse->getBody(),
            $artaxResponse->getProtocol(),
            $artaxResponse->getReason()
        );
    }
}
