<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiService
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;

    public function __construct(HttpClientInterface $httpClient, string $apiUrl)
    {
        $this->httpClient = $httpClient;
        $this->apiUrl = $apiUrl;
    }

    public function get(string $endpoint): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl . $endpoint);
        return $response->toArray();
    }
}
