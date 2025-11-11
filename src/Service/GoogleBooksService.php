<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GoogleBooksService
{
    private HttpClientInterface $client;
    private ?string $apiKey;
    private LoggerInterface $logger;
    private string $baseUrl = 'https://www.googleapis.com/books/v1/';

    private const MAX_RESULTS = 40;
    private const MAX_START_INDEX = 1000;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $googleBooksApiKey = null
    ) {
        $this->client = $httpClient;
        $this->apiKey = $googleBooksApiKey;
        $this->logger = $logger;
    }

    public function searchBooks(
        string $query,
        int $startIndex = 0,
        int $maxResults = 40
    ): array {
        $this->logger->info('[GoogleBooks] Search started', [
            'query' => $query,
            'startIndex' => $startIndex,
            'maxResults' => $maxResults
        ]);

        if (empty(trim($query))) {
            $this->logger->warning('[GoogleBooks] Empty query received');
            return ['items' => [], 'totalItems' => 0];
        }

        if ($startIndex > self::MAX_START_INDEX) {
            $this->logger->warning('[GoogleBooks] startIndex exceeds maximum', [
                'requested' => $startIndex,
                'maximum' => self::MAX_START_INDEX
            ]);
            return ['items' => [], 'totalItems' => 0];
        }

        try {
            $normalizedMaxResults = min($maxResults, self::MAX_RESULTS);

            $params = [
                'q' => $query,
                'startIndex' => $startIndex,
                'maxResults' => $normalizedMaxResults,
                'printType' => 'books',
                'orderBy' => 'relevance',
            ];

            if ($this->apiKey) {
                $params['key'] = $this->apiKey;
            }

            $url = $this->baseUrl . 'volumes?' . http_build_query($params);

            $this->logger->debug('[GoogleBooks] Request parameters', [
                'url' => $url,
                'params' => array_merge($params, ['key' => $this->apiKey ? 'set' : 'not_set'])
            ]);

            $response = $this->client->request('GET', $url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->error('[GoogleBooks] API returned non-200 status', [
                    'status' => $statusCode,
                    'query' => $query
                ]);
                return ['items' => [], 'totalItems' => 0];
            }

            $data = $response->toArray();

            $totalItems = $data['totalItems'] ?? 0;
            $itemsReturned = count($data['items'] ?? []);

            $this->logger->info('[GoogleBooks] Search response received', [
                'totalItems' => $totalItems,
                'itemsReturned' => $itemsReturned,
                'query' => $query
            ]);

            if (!empty($data['items']) && $itemsReturned < 3) {
                foreach (array_slice($data['items'], 0, 3) as $index => $item) {
                    $volumeInfo = $item['volumeInfo'] ?? [];
                    $this->logger->debug('[GoogleBooks] Book found', [
                        'index' => $index + 1,
                        'title' => $volumeInfo['title'] ?? 'No title',
                        'authors' => $volumeInfo['authors'] ?? [],
                        'id' => $item['id'] ?? 'No ID'
                    ]);
                }
            }

            $normalizedBooks = $this->normalizeBooks($data['items'] ?? []);

            $this->logger->info('[GoogleBooks] Books normalized successfully', [
                'count' => count($normalizedBooks),
                'query' => $query
            ]);

            return [
                'items' => $normalizedBooks,
                'totalItems' => $totalItems
            ];

        } catch (\Exception $e) {
            $this->logger->error('[GoogleBooks] Search failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'exception' => get_class($e)
            ]);
            return ['items' => [], 'totalItems' => 0];
        }
    }

    public function getBookByIsbn(string $isbn): ?array
    {
        $this->logger->info('[GoogleBooks] ISBN lookup started', ['isbn' => $isbn]);

        try {
            $result = $this->searchBooks("isbn:{$isbn}", 0, 1);

            if (!empty($result['items'])) {
                $this->logger->info('[GoogleBooks] Book found by ISBN', [
                    'isbn' => $isbn,
                    'title' => $result['items'][0]['titulo'] ?? 'Unknown'
                ]);
                return $result['items'][0];
            }

            $this->logger->warning('[GoogleBooks] Book not found by ISBN', ['isbn' => $isbn]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('[GoogleBooks] ISBN lookup failed', [
                'isbn' => $isbn,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getBooksByCategory(string $category, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        $this->logger->debug('[GoogleBooks] Category search', [
            'category' => $category,
            'page' => $page,
            'size' => $size
        ]);
        return $this->searchBooks("subject:{$category}", $startIndex, $size);
    }

    public function getBooksByAuthor(string $author, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        $this->logger->debug('[GoogleBooks] Author search', [
            'author' => $author,
            'page' => $page,
            'size' => $size
        ]);
        return $this->searchBooks("inauthor:{$author}", $startIndex, $size);
    }

    public function getBooksByTitle(string $title, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        $this->logger->debug('[GoogleBooks] Title search', [
            'title' => $title,
            'page' => $page,
            'size' => $size
        ]);
        return $this->searchBooks("intitle:{$title}", $startIndex, $size);
    }

    private function normalizeBooks(array $items): array
    {
        return array_map(function ($item) {
            $volumeInfo = $item['volumeInfo'] ?? [];
            $saleInfo = $item['saleInfo'] ?? [];

            $isbn = $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? []);
            $price = null;
            $currency = 'USD';
            $rating = $volumeInfo['averageRating'] ?? null;
            $ratingsCount = $volumeInfo['ratingsCount'] ?? 0;

            if (isset($saleInfo['listPrice'])) {
                $price = $saleInfo['listPrice']['amount'] ?? null;
                $currency = $saleInfo['listPrice']['currencyCode'] ?? 'USD';
            }

            return [
                'id' => $item['id'] ?? null,
                'isbn' => $isbn ?? $item['id'],
                'titulo' => $volumeInfo['title'] ?? 'Sin título',
                'autor' => isset($volumeInfo['authors'])
                    ? implode(', ', $volumeInfo['authors'])
                    : 'Autor desconocido',
                'editorial' => $volumeInfo['publisher'] ?? null,
                'fechaPublicacion' => $volumeInfo['publishedDate'] ?? null,
                'descripcion' => $volumeInfo['description'] ?? null,
                'categoria' => isset($volumeInfo['categories'])
                    ? $volumeInfo['categories'][0]
                    : null,
                'categorias' => $volumeInfo['categories'] ?? [],
                'paginas' => $volumeInfo['pageCount'] ?? null,
                'idioma' => $volumeInfo['language'] ?? 'es',
                'imagen' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                'imagenGrande' => $volumeInfo['imageLinks']['large'] ??
                    $volumeInfo['imageLinks']['medium'] ??
                    $volumeInfo['imageLinks']['thumbnail'] ?? null,
                'precio' => $price,
                'moneda' => $currency,
                'disponible' => ($saleInfo['saleability'] ?? 'NOT_FOR_SALE') === 'FOR_SALE',
                'previewLink' => $volumeInfo['previewLink'] ?? null,
                'infoLink' => $volumeInfo['infoLink'] ?? null,
                'rating' => $rating,
                'ratings_count' => $ratingsCount,
            ];
        }, $items);
    }

    private function extractIsbn(array $identifiers): ?string
    {
        foreach ($identifiers as $identifier) {
            if ($identifier['type'] === 'ISBN_13') {
                return $identifier['identifier'];
            }
        }

        foreach ($identifiers as $identifier) {
            if ($identifier['type'] === 'ISBN_10') {
                return $identifier['identifier'];
            }
        }

        return null;
    }

    public function getPopularCategories(): array
    {
        return [
            'Fiction',
            'Mystery',
            'Science Fiction',
            'Fantasy',
            'Romance',
            'Thriller',
            'Biography',
            'History',
            'Self-Help',
            'Business',
            'Science',
            'Technology'
        ];
    }
}
