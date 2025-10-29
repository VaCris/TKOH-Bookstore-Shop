<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private SessionInterface $session;
    private const CART_KEY = 'shopping_cart';

    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();
    }

    // ==================== AGREGAR AL CARRITO ====================

    /**
     * Agregar libro al carrito
     */
    public function addItem(array $book, int $quantity = 1): void
    {
        $cart = $this->getCart();
        $isbn = $book['isbn'];

        if (isset($cart[$isbn])) {
            // Si ya existe, incrementar cantidad
            $cart[$isbn]['cantidad'] += $quantity;
        } else {
            // Si no existe, agregar nuevo item
            $cart[$isbn] = [
                'isbn' => $book['isbn'],
                'titulo' => $book['titulo'],
                'autor' => $book['autor'],
                'imagen' => $book['imagen'] ?? $book['imagenGrande'] ?? null,
                'precio' => $this->extractPrice($book),
                'cantidad' => $quantity
            ];
        }

        $this->saveCart($cart);
    }

    // ==================== ACTUALIZAR CANTIDAD ====================

    /**
     * Actualizar cantidad de un item
     */
    public function updateQuantity(string $isbn, int $quantity): void
    {
        $cart = $this->getCart();

        if ($quantity <= 0) {
            $this->removeItem($isbn);
            return;
        }

        if ($quantity > 10) {
            $quantity = 10; // Máximo 10 unidades
        }

        if (isset($cart[$isbn])) {
            $cart[$isbn]['cantidad'] = $quantity;
            $this->saveCart($cart);
        }
    }

    // ==================== ELIMINAR ITEM ====================

    /**
     * Eliminar item del carrito
     */
    public function removeItem(string $isbn): void
    {
        $cart = $this->getCart();

        if (isset($cart[$isbn])) {
            unset($cart[$isbn]);
            $this->saveCart($cart);
        }
    }

    // ==================== OBTENER CARRITO ====================

    /**
     * Obtener todo el carrito
     */
    public function getCart(): array
    {
        return $this->session->get(self::CART_KEY, []);
    }

    /**
     * Obtener un item específico
     */
    public function getItem(string $isbn): ?array
    {
        $cart = $this->getCart();
        return $cart[$isbn] ?? null;
    }

    // ==================== VACIAR CARRITO ====================

    /**
     * Vaciar todo el carrito
     */
    public function clearCart(): void
    {
        $this->session->remove(self::CART_KEY);
    }

    // ==================== CÁLCULOS ====================

    /**
     * Calcular total del carrito
     */
    public function getTotal(): float
    {
        $cart = $this->getCart();
        $total = 0.0;

        foreach ($cart as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }

        return round($total, 2);
    }

    /**
     * Calcular subtotal de un item
     */
    public function getItemSubtotal(string $isbn): float
    {
        $item = $this->getItem($isbn);

        if (!$item) {
            return 0.0;
        }

        return round($item['precio'] * $item['cantidad'], 2);
    }

    /**
     * Contar items en el carrito (suma de cantidades)
     */
    public function getItemCount(): int
    {
        $cart = $this->getCart();
        $count = 0;

        foreach ($cart as $item) {
            $count += $item['cantidad'];
        }

        return $count;
    }

    /**
     * Contar productos únicos en el carrito
     */
    public function getUniqueItemCount(): int
    {
        return count($this->getCart());
    }

    /**
     * Calcular costo de envío
     */
    public function getShippingCost(): float
    {
        $total = $this->getTotal();

        // Envío gratis para pedidos >= $50
        if ($total >= 50.0) {
            return 0.0;
        }

        return 5.0;
    }

    /**
     * Calcular total final (con envío)
     */
    public function getFinalTotal(): float
    {
        return round($this->getTotal() + $this->getShippingCost(), 2);
    }

    // ==================== VALIDACIONES ====================

    /**
     * Verificar si el carrito está vacío
     */
    public function isEmpty(): bool
    {
        return empty($this->getCart());
    }

    /**
     * Verificar si un libro está en el carrito
     */
    public function hasItem(string $isbn): bool
    {
        $cart = $this->getCart();
        return isset($cart[$isbn]);
    }

    // ==================== HELPERS PRIVADOS ====================

    /**
     * Guardar carrito en sesión
     */
    private function saveCart(array $cart): void
    {
        $this->session->set(self::CART_KEY, $cart);
    }

    /**
     * Extraer precio del libro (de tu API o Google Books)
     */
    private function extractPrice(array $book): float
    {
        // Prioridad: precio de tu API > precio de Google Books > default
        if (isset($book['precio']) && is_numeric($book['precio'])) {
            return (float) $book['precio'];
        }

        // Precio por defecto
        return 25.0;
    }

    // ==================== MÉTODOS ÚTILES ====================

    /**
     * Obtener resumen del carrito (para mostrar en navbar, etc.)
     */
    public function getSummary(): array
    {
        return [
            'itemCount' => $this->getItemCount(),
            'uniqueItems' => $this->getUniqueItemCount(),
            'subtotal' => $this->getTotal(),
            'shipping' => $this->getShippingCost(),
            'total' => $this->getFinalTotal(),
            'isEmpty' => $this->isEmpty()
        ];
    }

    /**
     * Preparar datos del carrito para checkout/pago
     */
    public function prepareForCheckout(): array
    {
        $cart = $this->getCart();
        $items = [];

        foreach ($cart as $item) {
            $items[] = [
                'isbn' => $item['isbn'],
                'titulo' => $item['titulo'],
                'autor' => $item['autor'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio'],
                'subtotal' => $this->getItemSubtotal($item['isbn'])
            ];
        }

        return [
            'items' => $items,
            'subtotal' => $this->getTotal(),
            'shipping' => $this->getShippingCost(),
            'total' => $this->getFinalTotal(),
            'itemCount' => $this->getItemCount()
        ];
    }

    /**
     * Aplicar descuentos
     */
    public function applyDiscount(string $code): bool
    {
        return false;
    }
}
