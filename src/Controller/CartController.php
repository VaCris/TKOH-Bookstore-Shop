<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\BookstoreApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/carrito')]
class CartController extends AbstractController
{
    private CartService $cartService;
    private BookstoreApiService $apiService;

    public function __construct(
        CartService $cartService,
        BookstoreApiService $apiService
    ) {
        $this->cartService = $cartService;
        $this->apiService = $apiService;
    }

    /**
     * Mostrar el carrito de compras
     */
    #[Route('/', name: 'cart_index')]
    public function index(): Response
    {
        $cart = $this->cartService->getCart();
        $total = $this->cartService->getTotal();
        $itemCount = $this->cartService->getItemCount();

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'total' => $total,
            'itemCount' => $itemCount
        ]);
    }

    /**
     * Agregar libro al carrito (AJAX)
     */
    #[Route('/agregar', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $isbn = $data['isbn'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (!$isbn) {
            return $this->json([
                'success' => false,
                'message' => 'ISBN no proporcionado'
            ], 400);
        }

        $book = $this->apiService->getBookByIsbn($isbn);

        if (!$book) {
            return $this->json([
                'success' => false,
                'message' => 'Libro no encontrado'
            ], 404);
        }

        if ($book['precio'] === null || $book['precio'] === 0) {
            return $this->json([
                'success' => false,
                'message' => 'Este libro no está disponible para su compra'
            ], 409);
        }

        $this->cartService->addItem($book, $quantity);

        return $this->json([
            'success' => true,
            'message' => 'Libro agregado al carrito',
            'itemCount' => $this->cartService->getItemCount(),
            'total' => $this->cartService->getTotal()
        ]);
    }

    /**
     * Actualizar cantidad de un item
     */
    #[Route('/actualizar', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $isbn = $data['isbn'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (!$isbn) {
            return $this->json([
                'success' => false,
                'message' => 'ISBN no proporcionado'
            ], 400);
        }

        $this->cartService->updateQuantity($isbn, (int) $quantity);

        return $this->json([
            'success' => true,
            'message' => 'Cantidad actualizada',
            'itemCount' => $this->cartService->getItemCount(),
            'total' => $this->cartService->getTotal()
        ]);
    }

    /**
     * Eliminar item del carrito (AJAX)
     */
    #[Route('/eliminar', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $isbn = $data['isbn'] ?? null;

        if (!$isbn) {
            return $this->json([
                'success' => false,
                'message' => 'ISBN no proporcionado'
            ], 400);
        }

        $this->cartService->removeItem($isbn);

        return $this->json([
            'success' => true,
            'message' => 'Libro eliminado del carrito',
            'itemCount' => $this->cartService->getItemCount(),
            'total' => $this->cartService->getTotal()
        ]);
    }

    /**
     * Vaciar todo el carrito
     */
    #[Route('/vaciar', name: 'cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        $this->cartService->clearCart();
        $this->addFlash('success', 'Carrito vaciado correctamente.');

        return $this->redirectToRoute('catalog_index');
    }

    /**
     * Obtener cantidad de items en el carrito (para el badge)
     */
    #[Route('/count', name: 'cart_count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        return $this->json([
            'count' => $this->cartService->getItemCount()
        ]);
    }
}
