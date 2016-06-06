<?php
namespace ArtaxGuzzleBridge;

use Amp\Artax\HttpClient;
use Amp\Artax\Request;
use Amp\Artax\ResourceIterator;
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
        $guzzleResponsePromise = new Promise;

        $artaxRequest = $this->convertRequest($request, $options);
        $artaxResponsePromise = $this->artaxClient->request($artaxRequest);

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

        if ($body = $request->getBody()) {
            if (!$bodyResource = $body->detach()) {
                $bodyResource = fopen('php://temp', 'r+');
                $bodyStream = \GuzzleHttp\Psr7\stream_for($bodyResource);
                \GuzzleHttp\Psr7\copy_to_stream($request->getBody(), $bodyStream);
                if (!$request->getBody()->eof()) {
                    throw new \LogicException('Request body must be complete before attempting to send request.');
                }
                rewind($bodyResource);
            }

            $artaxRequest->setBody(new ResourceIterator($bodyResource));
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
