<?php

namespace App\Controller;

use App\Service\GoogleBooksService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class CatalogController extends AbstractController
{
    private GoogleBooksService $googleBooks;
    private LoggerInterface $logger;

    private const ITEMS_PER_PAGE = 20;
    private const MAX_PAGES = 5;
    private const FEATURED_ITEMS = 5;
    private const CATEGORY_ITEMS = 10;

    public function __construct(
        GoogleBooksService $googleBooks,
        LoggerInterface $logger
    ) {
        $this->googleBooks = $googleBooks;
        $this->logger = $logger;
    }

    #[Route('/', name: 'catalog_index')]
    public function index(Request $request): Response
    {
        $this->logger->info('[Catalog] Index page accessed');

        $page = max(1, (int) $request->query->get('page', 1));
        $search = $request->query->get('q', '') ?: $request->query->get('search', '');
        $category = $request->query->get('categoria', '');

        if (!empty($search)) {
            $this->logger->info('[Catalog] Search query received', ['query' => $search]);
            return $this->performSearch($search, $page);
        }

        if (!empty($category)) {
            $this->logger->info('[Catalog] Category filter applied', ['category' => $category]);
            return $this->filterByCategory($category, $page);
        }

        return $this->showHomepage();
    }

    #[Route('/libro/{isbn}', name: 'catalog_book_detail')]
    public function detail(string $isbn): Response
    {
        $this->logger->info('[Catalog] Book detail requested', ['isbn' => $isbn]);

        $book = $this->googleBooks->getBookByIsbn($isbn);

        if (!$book) {
            $this->logger->warning('[Catalog] Book not found', ['isbn' => $isbn]);
            $this->addFlash('error', 'Libro no encontrado.');
            return $this->redirectToRoute('catalog_index');
        }

        $this->logger->info('[Catalog] Book found', [
            'isbn' => $isbn,
            'title' => $book['titulo'] ?? 'Unknown'
        ]);

        $relatedBooks = $this->getRelatedBooks($book);

        return $this->render('catalog/detail.html.twig', [
            'book' => $book,
            'relatedBooks' => $relatedBooks
        ]);
    }

    #[Route('/buscar', name: 'catalog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '') ?: $request->query->get('search', '');

        $this->logger->info('[Catalog] Search initiated', ['query' => $query]);

        if (strlen(trim($query)) < 2) {
            $this->logger->warning('[Catalog] Search query too short', [
                'query' => $query,
                'length' => strlen($query)
            ]);
            $this->addFlash('warning', 'Por favor ingresa al menos 2 caracteres.');
            return $this->redirectToRoute('catalog_index');
        }

        return $this->redirectToRoute('catalog_index', ['q' => $query]);
    }

    #[Route('/categoria/{category}', name: 'catalog_by_category')]
    public function byCategory(string $category, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));

        $this->logger->info('[Catalog] Category navigation', [
            'category' => $category,
            'page' => $page
        ]);

        return $this->redirectToRoute('catalog_index', [
            'categoria' => $category,
            'page' => $page
        ]);
    }

    private function showHomepage(): Response
    {
        $this->logger->info('[Catalog] Loading homepage');

        $featuredResult = $this->googleBooks->searchBooks('bestseller', 0, self::FEATURED_ITEMS);
        $featuredBooks = $featuredResult['items'] ?? [];

        $categories = [
            'Fiction' => 'Ficción',
            'Science Fiction' => 'Ciencia Ficción',
            'Fantasy' => 'Fantasía',
            'Mystery' => 'Misterio',
            'Romance' => 'Romance',
            'Biography' => 'Biografías'
        ];

        $categorySections = [];
        foreach ($categories as $categoryKey => $categoryName) {
            $result = $this->googleBooks->getBooksByCategory($categoryKey, 0, self::CATEGORY_ITEMS);
            $categorySections[] = [
                'key' => $categoryKey,
                'name' => $categoryName,
                'books' => $result['items'] ?? []
            ];
        }

        $this->logger->debug('[Catalog] Homepage sections loaded', [
            'sections' => count($categorySections)
        ]);

        return $this->render('catalog/home.html.twig', [
            'featuredBooks' => $featuredBooks,
            'sections' => $categorySections,
            'categories' => $this->formatCategories($this->googleBooks->getPopularCategories())
        ]);
    }

    private function performSearch(string $query, int $page): Response
    {
        $this->logger->info('[Catalog] Performing search', [
            'query' => $query,
            'page' => $page
        ]);

        $startIndex = ($page - 1) * self::ITEMS_PER_PAGE;
        $result = $this->googleBooks->searchBooks($query, $startIndex, self::ITEMS_PER_PAGE);
        $books = $result['items'] ?? [];

        if ($page > 1 && empty($books)) {
            $this->logger->warning('[Catalog] No results on requested page', [
                'query' => $query,
                'page' => $page
            ]);

            return $this->render('catalog/search.html.twig', [
                'books' => [],
                'search' => $query,
                'page' => $page,
                'totalPages' => $page - 1,
                'totalItems' => 0,
                'error' => 'No hay más resultados disponibles en esta búsqueda.',
            ]);
        }

        if (empty($books)) {
            $this->logger->info('[Catalog] No results found', ['query' => $query]);

            return $this->render('catalog/search.html.twig', [
                'books' => [],
                'search' => $query,
                'page' => 1,
                'totalPages' => 0,
                'totalItems' => 0,
                'error' => 'No se encontraron resultados para tu búsqueda.',
            ]);
        }

        $totalPages = self::MAX_PAGES;

        $this->logger->info('[Catalog] Search completed', [
            'query' => $query,
            'page' => $page,
            'totalPages' => $totalPages,
            'resultsCount' => count($books)
        ]);

        return $this->render('catalog/search.html.twig', [
            'books' => $books,
            'search' => $query,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => count($books),
            'categories' => $this->formatCategories($this->googleBooks->getPopularCategories())
        ]);
    }

    private function filterByCategory(string $category, int $page): Response
    {
        $this->logger->info('[Catalog] Filtering by category', [
            'category' => $category,
            'page' => $page
        ]);

        $startIndex = ($page - 1) * self::ITEMS_PER_PAGE;
        $result = $this->googleBooks->getBooksByCategory($category, $startIndex, self::ITEMS_PER_PAGE);
        $books = $result['items'] ?? [];

        if ($page > 1 && empty($books)) {
            $this->logger->warning('[Catalog] No results on page for category', [
                'category' => $category,
                'page' => $page
            ]);

            return $this->render('catalog/search.html.twig', [
                'books' => [],
                'category' => $category,
                'page' => $page,
                'totalPages' => $page - 1,
                'totalItems' => 0,
                'error' => 'No hay más libros en esta categoría.',
            ]);
        }

        if (empty($books)) {
            $this->logger->info('[Catalog] No results for category', ['category' => $category]);

            return $this->render('catalog/search.html.twig', [
                'books' => [],
                'category' => $category,
                'page' => 1,
                'totalPages' => 0,
                'totalItems' => 0,
                'error' => 'No hay libros en esta categoría.',
            ]);
        }

        $totalPages = self::MAX_PAGES;

        $this->logger->info('[Catalog] Category filter completed', [
            'category' => $category,
            'page' => $page,
            'totalPages' => $totalPages
        ]);

        return $this->render('catalog/search.html.twig', [
            'books' => $books,
            'category' => $category,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => count($books),
            'categories' => $this->formatCategories($this->googleBooks->getPopularCategories())
        ]);
    }

    private function calculateRealTotal(string $query): int
    {
        $this->logger->debug('[Catalog] Calculating real total', ['query' => $query]);
        return self::MAX_PAGES * self::ITEMS_PER_PAGE;
    }

    private function getRelatedBooks(array $book): array
    {
        if (empty($book['categoria'])) {
            return [];
        }

        $this->logger->debug('[Catalog] Looking for related books', [
            'category' => $book['categoria']
        ]);

        $result = $this->googleBooks->getBooksByCategory($book['categoria'], 0, 8);
        $relatedBooks = array_filter($result['items'] ?? [], function ($b) use ($book) {
            return $b['isbn'] !== $book['isbn'];
        });

        $relatedBooks = array_slice($relatedBooks, 0, 6);

        $this->logger->debug('[Catalog] Related books found', [
            'count' => count($relatedBooks)
        ]);

        return $relatedBooks;
    }

    private function formatCategories(array $categories): array
    {
        return array_map(function ($category, $index) {
            return [
                'id' => $index + 1,
                'nombre' => $category
            ];
        }, $categories, array_keys($categories));
    }
}
