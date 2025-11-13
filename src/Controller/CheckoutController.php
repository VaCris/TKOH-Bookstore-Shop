<?php

namespace App\Controller;

use App\Service\StripeService;
use App\Service\GoogleBooksService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class CheckoutController extends AbstractController
{
    private StripeService $stripeService;
    private GoogleBooksService $googleBooksService;
    private LoggerInterface $logger;

    public function __construct(
        StripeService $stripeService,
        GoogleBooksService $googleBooksService,
        LoggerInterface $logger
    ) {
        $this->stripeService = $stripeService;
        $this->googleBooksService = $googleBooksService;
        $this->logger = $logger;
    }

    #[Route('/checkout', name: 'checkout_index')]
    public function index(Request $request): Response
    {
        $this->logger->info('[Checkout] Guest checkout access');

        $cart = $request->getSession()->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('warning', 'Tu carrito está vacío.');
            return $this->redirectToRoute('catalog_index');
        }

        $subtotal = 0;
        $cartItems = [];

        foreach ($cart as $isbn => $item) {
            $cartItems[] = [
                'isbn' => $isbn,
                'titulo' => $item['titulo'] ?? 'Unknown',
                'autor' => $item['autor'] ?? 'Unknown',
                'precio' => $item['precio'] ?? 0,
                'moneda' => $item['moneda'] ?? 'USD',
                'cantidad' => $item['cantidad'] ?? 1,
                'imagen' => $item['imagen'] ?? '/images/200x300-placeholder.jpg',
            ];
            $subtotal += ($item['precio'] ?? 0) * ($item['cantidad'] ?? 1);
        }

        $shipping = $subtotal >= 50 ? 0 : 5;
        $total = $subtotal + $shipping;

        return $this->render('checkout/index.html.twig', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $total,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'],
            'page_name' => 'checkout',
            'is_guest' => true,
        ]);
    }

    #[Route('/api/checkout/create-session', name: 'api_checkout_create_session', methods: ['POST'])]
    public function createSession(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $this->logger->info('[Checkout API] Session creation requested', [
            'items_count' => count($data['items'] ?? [])
        ]);

        if (empty($data['items'])) {
            return $this->json(['error' => 'Carrito vacío'], 400);
        }

        try {
            $cartItems = [];
            foreach ($data['items'] as $item) {
                $isbn = $item['isbn'] ?? null;
                $cantidad = $item['cantidad'] ?? 1;

                if (!$isbn) {
                    $this->logger->warning('[Checkout API] Item without ISBN', $item);
                    continue;
                }
                $book = $this->googleBooksService->getBookByIsbn($isbn);

                if (!$book || $book['precio'] === null || $book['precio'] <= 0) {
                    $this->logger->warning('[Checkout API] Book not found or no price', ['isbn' => $isbn]);
                    continue;
                }

                $cartItems[] = [
                    'isbn' => $isbn,
                    'titulo' => $book['titulo'],
                    'autor' => $book['autor'],
                    'imagen' => $book['imagen'],
                    'precio' => $book['precio'],
                    'moneda' => $book['moneda'],
                    'cantidad' => $cantidad,
                ];
            }

            if (empty($cartItems)) {
                return $this->json([
                    'error' => 'No hay items válidos para procesar'
                ], 400);
            }

            $sessionId = $this->stripeService->createCheckoutSession(
                $cartItems,
                $this->generateUrl('api_checkout_success', [], true),
                $this->generateUrl('api_checkout_cancel', [], true)
            );

            $this->logger->info('[Checkout API] Session created successfully', [
                'session_id' => $sessionId,
                'items' => count($cartItems)
            ]);

            return $this->json([
                'success' => true,
                'sessionId' => $sessionId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[Checkout API] Failed to create session', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/checkout/success', name: 'api_checkout_success')]
    public function success(Request $request): Response
    {
        $this->logger->info('[Checkout API] Payment successful');

        $request->getSession()->remove('cart');

        return $this->json([
            'success' => true,
            'message' => 'Pago completado exitosamente'
        ]);
    }

    #[Route('/api/checkout/cancel', name: 'api_checkout_cancel')]
    public function cancel(): Response
    {
        $this->logger->warning('[Checkout API] Payment cancelled');

        return $this->json([
            'success' => false,
            'message' => 'Pago cancelado'
        ]);
    }
}
