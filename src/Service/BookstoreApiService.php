<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class BookstoreApiService
{
    private HttpClientInterface $client;
    private GoogleBooksService $googleBooks;
    private RequestStack $requestStack;
    private ?string $token = null;

    public function __construct(
        HttpClientInterface $bookstoreApiClient,
        GoogleBooksService $googleBooks,
        RequestStack $requestStack
    ) {
        $this->client = $bookstoreApiClient;
        $this->googleBooks = $googleBooks;
        $this->requestStack = $requestStack;
        $session = $this->requestStack->getSession();
        $this->token = $session->get('jwt_token');
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
        $session = $this->requestStack->getSession();
        if ($token) {
            $session->set('jwt_token', $token);
        } else {
            $session->remove('jwt_token');
        }
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function isAuthenticated(): bool
    {
        return $this->token !== null;
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(string $email, string $password): array
    {
        try {
            $response = $this->client->request('POST', '/auth/login', [
                'json' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]);

            $data = $response->toArray();

            if ($data['success'] && isset($data['data']['token'])) {
                $this->setToken($data['data']['token']);

                $session = $this->requestStack->getSession();
                $session->set('user_data', $data['data']['usuario']);
            }

            return $data;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al iniciar sesión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(array $userData): array
    {
        try {
            $response = $this->client->request('POST', '/auth/register', [
                'json' => $userData
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al registrar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $this->setToken(null);
        $session = $this->requestStack->getSession();
        $session->clear();
    }

    /**
     * GET /api/v1/libros (con paginación y búsqueda)
     */
    public function getBooks(
        int $page = 0,
        int $size = 40,
        ?string $search = null,
        ?int $categoriaId = null,
        ?int $editorialId = null,
        string $sort = 'titulo,asc'
    ): array {
        try {
            $params = [
                'page' => $page,
                'size' => $size,
                'sort' => $sort
            ];

            if ($search) {
                $params['search'] = $search;
            }
            if ($categoriaId) {
                $params['categoriaId'] = $categoriaId;
            }
            if ($editorialId) {
                $params['editorialId'] = $editorialId;
            }

            $response = $this->client->request('GET', '/libros', [
                'query' => $params,
                'headers' => $this->getHeaders(),
                'timeout' => 5
            ]);

            $data = $response->toArray();
            if (empty($data['data']['content']) && $search) {
                $googleResults = $this->googleBooks->searchBooks($search, $page * $size, $size);
                return [
                    'success' => true,
                    'data' => [
                        'content' => $googleResults['items'],
                        'totalElements' => $googleResults['totalItems'],
                        'totalPages' => ceil($googleResults['totalItems'] / $size)
                    ]
                ];
            }

            return $data;

        } catch (\Exception $e) {
            if ($search) {
                $googleResults = $this->googleBooks->searchBooks($search, $page * $size, $size);
                return [
                    'success' => true,
                    'data' => [
                        'content' => $googleResults['items'],
                        'totalElements' => $googleResults['totalItems'],
                        'totalPages' => ceil($googleResults['totalItems'] / $size)
                    ]
                ];
            }

            return [
                'success' => false,
                'data' => ['content' => [], 'totalPages' => 0, 'totalElements' => 0]
            ];
        }
    }

    /**
     * GET /api/v1/libros/{isbn}
     */
    public function getBookByIsbn(string $isbn): ?array
    {
        try {
            $response = $this->client->request('GET', "/libros/{$isbn}", [
                'headers' => $this->getHeaders()
            ]);
            $data = $response->toArray();
            return $data['data'] ?? null;
        } catch (\Exception $e) {
            return $this->googleBooks->getBookByIsbn($isbn);
        }
    }

    /**
     * GET /api/v1/categorias
     */
    public function getCategories(): array
    {
        try {
            $response = $this->client->request('GET', '/categorias', [
                'query' => ['page' => 0, 'size' => 100],
                'headers' => $this->getHeaders()
            ]);
            $data = $response->toArray();
            return $data['data']['content'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * GET /api/v1/editoriales
     */
    public function getEditorials(): array
    {
        try {
            $response = $this->client->request('GET', '/editoriales', [
                'query' => ['page' => 0, 'size' => 100],
                'headers' => $this->getHeaders()
            ]);
            $data = $response->toArray();
            return $data['data']['content'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * GET /api/v1/users/me
     */
    public function getProfile(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        try {
            $response = $this->client->request('GET', '/users/me', [
                'headers' => $this->getHeaders()
            ]);
            $data = $response->toArray();
            return $data['data'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * PUT /api/v1/users/me
     */
    public function updateProfile(array $profileData): array
    {
        try {
            $response = $this->client->request('PUT', '/users/me', [
                'json' => $profileData,
                'headers' => $this->getHeaders()
            ]);
            return $response->toArray();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar perfil'
            ];
        }
    }

    private function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    public function getUserData(): ?array
    {
        $session = $this->requestStack->getSession();
        return $session->get('user_data');
    }
}
