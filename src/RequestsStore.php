<?php

namespace Scoutapm;

use Scoutapm\Events\Request;
use Scoutapm\Exception\Request\DuplicateRequestNameException;

class RequestsStore implements \JsonSerializable
{
    protected $store = [];

    public function list() : array
    {
        return $this->store;
    }

    public function isEmpty() : bool
    {
        return empty($this->store);
    }

    public function clear()
    {
        $this->store = [];
    }

    public function register(Request $request)
    {
        $name = $request->getRequestName();

        if (isset($this->store[$name])) {
            throw new DuplicateRequestNameException($name);
        }

        $this->store[$name] = $request;
    }

    public function get(string $name)
    {
        return $this->store[$name] ?? null;
    }

    public function jsonSerialize() : array
    {
        return array_values($this->store);
    }
}
