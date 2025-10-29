<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\BookstoreApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    private CartService $cartService;
    private BookstoreApiService $apiService;

    public function __construct(
        CartService $cartService,
        BookstoreApiService $apiService
    ) {
        $this->cartService = $cartService;
        $this->apiService = $apiService;
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    /**
     * Página de checkout
     */
    #[Route('/', name: 'checkout_index')]
    public function index(): Response
    {
        if (!$this->apiService->isAuthenticated()) {
            $this->addFlash('warning', 'Debes iniciar sesión para continuar.');
            return $this->redirectToRoute('auth_login');
        }

        if ($this->cartService->isEmpty()) {
            $this->addFlash('warning', 'Tu carrito está vacío.');
            return $this->redirectToRoute('catalog_index');
        }

        $userData = $this->apiService->getUserData();
        $checkoutData = $this->cartService->prepareForCheckout();

        return $this->render('checkout/index.html.twig', [
            'cart' => $checkoutData,
            'user' => $userData,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY']
        ]);
    }

    /**
     * Crear sesión de Stripe Checkout con items del carrito
     */
    #[Route('/create-session', name: 'checkout_create_session', methods: ['POST'])]
    public function createSession(): JsonResponse
    {
        if (!$this->apiService->isAuthenticated()) {
            return $this->json(['error' => 'No autenticado'], 401);
        }

        if ($this->cartService->isEmpty()) {
            return $this->json(['error' => 'Carrito vacío'], 400);
        }

        try {
            $userData = $this->apiService->getUserData();
            $cart = $this->cartService->getCart();
            $lineItems = [];

            foreach ($cart as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $item['titulo'],
                            'description' => 'Por ' . $item['autor'],
                            'images' => $item['imagen'] ? [$item['imagen']] : [],
                        ],
                        'unit_amount' => (int) ($item['precio'] * 100),
                    ],
                    'quantity' => $item['cantidad'],
                ];
            }

            $shippingCost = $this->cartService->getShippingCost();
            if ($shippingCost > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Envío',
                            'description' => 'Entrega a domicilio (3-5 días)',
                        ],
                        'unit_amount' => (int) ($shippingCost * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $this->generateUrl('checkout_success', [
                    'session_id' => '{CHECKOUT_SESSION_ID}'
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'customer_email' => $userData['email'] ?? null,
                'shipping_address_collection' => [
                    'allowed_countries' => ['US', 'CA', 'MX', 'PE', 'CO', 'AR', 'CL', 'ES'],
                ],
                'metadata' => [
                    'user_email' => $userData['email'] ?? '',
                    'order_id' => uniqid('order_', true),
                ],
            ]);

            return $this->json([
                'id' => $session->id,
                'url' => $session->url
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error al crear sesión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Página de éxito
     */
    #[Route('/success', name: 'checkout_success')]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');
        $this->cartService->clearCart();

        $this->addFlash('success', '¡Pago realizado con éxito! Gracias por tu compra.');

        return $this->render('checkout/success.html.twig', [
            'sessionId' => $sessionId
        ]);
    }

    /**
     * Página de cancelación
     */
    #[Route('/cancel', name: 'checkout_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('warning', 'Pago cancelado. Tu carrito sigue disponible.');
        return $this->redirectToRoute('cart_index');
    }
}
