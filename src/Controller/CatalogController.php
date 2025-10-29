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

    public function __construct(
        GoogleBooksService $googleBooks,
        LoggerInterface $logger
    ) {
        $this->googleBooks = $googleBooks;
        $this->logger = $logger;
    }

    /**
     * Página principal - Catálogo de libros
     */
    #[Route('/', name: 'catalog_index')]
    public function index(Request $request): Response
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $size = 10;
        $search = $request->query->get('search');
        $category = $request->query->get('categoria');

        if ($search || $category) {
            return $this->searchResults($request, $search, $category, $page);
        }

        $featuredResult = $this->googleBooks->searchBooks('bestseller', 0, 5);
        $featuredBooks = $featuredResult['items'] ?? [];

        $sections = [
            'Fiction' => 'Ficción',
            'Science Fiction' => 'Ciencia Ficción',
            'Fantasy' => 'Fantasía',
            'Mystery' => 'Misterio',
            'Romance' => 'Romance',
            'Biography' => 'Biografías'
        ];

        $categorySections = [];
        foreach ($sections as $categoryKey => $categoryName) {
            $result = $this->googleBooks->searchBooks("subject:{$categoryKey}", 0, 10);
            $categorySections[] = [
                'key' => $categoryKey,
                'name' => $categoryName,
                'books' => $result['items'] ?? []
            ];
        }

        return $this->render('catalog/home.html.twig', [
            'featuredBooks' => $featuredBooks,
            'sections' => $categorySections,
            'categories' => $this->formatCategories($this->googleBooks->getPopularCategories())
        ]);
    }

    /**
     * Resultados de búsqueda (vista tradicional)
     */
    private function searchResults(Request $request, ?string $search, ?string $category, int $page): Response
    {
        $size = 40;

        if ($category) {
            $result = $this->googleBooks->getBooksByCategory($category, $page, $size);
        } else {
            $result = $this->googleBooks->searchBooks($search, $page * $size, $size);
        }

        $books = $result['items'] ?? [];
        $totalItems = $result['totalItems'] ?? 0;
        $totalPages = $totalItems > 0 ? ceil($totalItems / $size) : 0;

        return $this->render('catalog/search.html.twig', [
            'books' => $books,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalElements' => $totalItems,
            'currentSearch' => $search,
            'currentCategoria' => $category,
            'categories' => $this->formatCategories($this->googleBooks->getPopularCategories())
        ]);
    }

    /**
     * Detalle de libro
     */
    #[Route('/libro/{isbn}', name: 'catalog_book_detail')]
    public function detail(string $isbn): Response
    {
        $this->logger->info('📖 [CATALOG] Solicitando detalle de libro', ['isbn' => $isbn]);

        $book = $this->googleBooks->getBookByIsbn($isbn);

        if (!$book) {
            $this->logger->warning('⚠️ [CATALOG] Libro no encontrado', ['isbn' => $isbn]);
            $this->addFlash('error', 'Libro no encontrado.');
            return $this->redirectToRoute('catalog_index');
        }

        $this->logger->info('✅ [CATALOG] Libro encontrado', [
            'isbn' => $isbn,
            'titulo' => $book['titulo'] ?? 'N/A'
        ]);

        $relatedBooks = [];
        if (isset($book['categoria'])) {
            $this->logger->info('🔍 Buscando libros relacionados', ['categoria' => $book['categoria']]);
            $result = $this->googleBooks->getBooksByCategory($book['categoria'], 0, 6);
            $relatedBooks = array_filter($result['items'] ?? [], function ($b) use ($isbn) {
                return $b['isbn'] !== $isbn;
            });
            $relatedBooks = array_slice($relatedBooks, 0, 5);
            $this->logger->info('📚 Libros relacionados encontrados', ['count' => count($relatedBooks)]);
        }

        return $this->render('catalog/detail.html.twig', [
            'book' => $book,
            'relatedBooks' => $relatedBooks
        ]);
    }

    /**
     * Búsqueda
     */
    #[Route('/buscar', name: 'catalog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');

        $this->logger->info('🔍 [CATALOG] Búsqueda iniciada', ['query' => $query]);

        if (!$query) {
            $this->logger->warning('⚠️ [CATALOG] Búsqueda vacía, redirigiendo');
            return $this->redirectToRoute('catalog_index');
        }

        // ✅ CORRECCIÓN: Redirigir con 'search' en lugar de 'q'
        return $this->redirectToRoute('catalog_index', [
            'search' => $query
        ]);
    }

    /**
     * Listar por categoría
     */
    #[Route('/categoria/{category}', name: 'catalog_by_category')]
    public function byCategory(string $category, Request $request): Response
    {
        $page = max(0, (int) $request->query->get('page', 0));

        $this->logger->info('📚 [CATALOG] Navegando a categoría', [
            'category' => $category,
            'page' => $page
        ]);

        return $this->redirectToRoute('catalog_index', [
            'categoria' => $category,
            'page' => $page
        ]);
    }

    /**
     * Formatear categorías para el selector
     */
    private function formatCategories(array $categories): array
    {
        return array_map(function ($cat, $index) {
            return [
                'id' => $index + 1,
                'nombre' => $cat
            ];
        }, $categories, array_keys($categories));
    }
}
