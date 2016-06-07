<?php
namespace ArtaxGuzzleBridge;

use Amp\Artax\StreamIterator;
use Psr\Http\Message\StreamInterface;

class PsrStreamIterator extends StreamIterator
{
    private $stream;
    private $currentCache;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * @return string
     * @throws \RuntimeException If file cannot be read
     */
    public function current()
    {
        if (isset($this->currentCache)) {
            $current = $this->currentCache;
        } else {
            $current = $this->currentCache = $this->stream->read($this->readSize);
        }

        return $current;
    }

    public function key()
    {
        return 0;
    }

    public function next()
    {
        return $this->currentCache = null;
    }

    public function valid()
    {
        return !$this->stream->eof();
    }

    public function rewind()
    {
        $this->stream->rewind();
    }
}
