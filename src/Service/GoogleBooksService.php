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

    public function __construct(
        HttpClientInterface $httpClient, // ← Cambiar nombre del parámetro
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
        $this->logger->info('🔍 [GOOGLE BOOKS] Iniciando búsqueda', [
            'query' => $query,
            'startIndex' => $startIndex,
            'maxResults' => $maxResults
        ]);

        if (empty(trim($query))) {
            $this->logger->error('❌ Query vacío recibido');
            return ['items' => [], 'totalItems' => 0];
        }

        try {
            $params = [
                'q' => $query,
                'startIndex' => $startIndex,
                'maxResults' => min($maxResults, 40),
                'printType' => 'books'
            ];

            if ($this->apiKey) {
                $params['key'] = $this->apiKey;
            }

            $this->logger->info('📡 Parámetros de petición', $params);

            // ✅ CORRECCIÓN: URL completa
            $url = $this->baseUrl . 'volumes?' . http_build_query($params);
            $this->logger->info('🌐 URL completa', ['url' => $url]);

            $response = $this->client->request('GET', $url);

            $statusCode = $response->getStatusCode();
            $this->logger->info('✅ Respuesta recibida', ['status' => $statusCode]);

            if ($statusCode !== 200) {
                $this->logger->error('❌ Status code no exitoso', ['status' => $statusCode]);
                return ['items' => [], 'totalItems' => 0];
            }

            $data = $response->toArray();

            $totalItems = $data['totalItems'] ?? 0;
            $itemsCount = count($data['items'] ?? []);

            $this->logger->info('📚 Datos recibidos', [
                'totalItems' => $totalItems,
                'itemsReturned' => $itemsCount
            ]);

            if (!empty($data['items'])) {
                foreach (array_slice($data['items'], 0, 3) as $index => $item) {
                    $volumeInfo = $item['volumeInfo'] ?? [];
                    $this->logger->info("📖 Libro #" . ($index + 1), [
                        'title' => $volumeInfo['title'] ?? 'Sin título',
                        'authors' => $volumeInfo['authors'] ?? []
                    ]);
                }
            }

            $normalizedBooks = $this->normalizeBooks($data['items'] ?? []);
            $this->logger->info('✅ Libros normalizados', ['count' => count($normalizedBooks)]);

            return [
                'items' => $normalizedBooks,
                'totalItems' => $totalItems
            ];

        } catch (\Exception $e) {
            $this->logger->error('❌ Error general', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            return ['items' => [], 'totalItems' => 0];
        }
    }

    public function getBookByIsbn(string $isbn): ?array
    {
        $this->logger->info('🔍 [GOOGLE BOOKS] Buscando por ISBN', ['isbn' => $isbn]);

        try {
            $result = $this->searchBooks("isbn:{$isbn}", 0, 1);

            if (!empty($result['items'])) {
                $this->logger->info('✅ Libro encontrado por ISBN');
                return $result['items'][0];
            }

            $this->logger->warning('⚠️ No se encontró libro con ISBN');
            return null;
        } catch (\Exception $e) {
            $this->logger->error('❌ Error buscando por ISBN', [
                'isbn' => $isbn,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getBooksByCategory(string $category, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        return $this->searchBooks("subject:{$category}", $startIndex, $size);
    }

    public function getBooksByAuthor(string $author, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        return $this->searchBooks("inauthor:{$author}", $startIndex, $size);
    }

    public function getBooksByTitle(string $title, int $page = 0, int $size = 40): array
    {
        $startIndex = $page * $size;
        return $this->searchBooks("intitle:{$title}", $startIndex, $size);
    }

    private function normalizeBooks(array $items): array
    {
        return array_map(function ($item) {
            $volumeInfo = $item['volumeInfo'] ?? [];
            $saleInfo = $item['saleInfo'] ?? [];

            $isbn = $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? []);
            $precio = $saleInfo['listPrice']['amount'] ?? 25.00;

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
                'precio' => $precio,
                'moneda' => $saleInfo['listPrice']['currencyCode'] ?? 'USD',
                'disponible' => ($saleInfo['saleability'] ?? 'NOT_FOR_SALE') === 'FOR_SALE',
                'previewLink' => $volumeInfo['previewLink'] ?? null,
                'infoLink' => $volumeInfo['infoLink'] ?? null
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
