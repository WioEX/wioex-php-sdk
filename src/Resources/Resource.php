<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Client;
use Wioex\SDK\Http\Response;

abstract class Resource
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected function get(string $path, array $query = []): Response
    {
        return $this->client->get($path, $query);
    }

    protected function post(string $path, array $data = []): Response
    {
        return $this->client->post($path, $data);
    }

    protected function put(string $path, array $data = []): Response
    {
        return $this->client->put($path, $data);
    }

    protected function delete(string $path, array $query = []): Response
    {
        return $this->client->delete($path, $query);
    }
}
