<?php

namespace App\Controller;

use App\Service\BookstoreApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/auth')]
class AuthController extends AbstractController
{
    private BookstoreApiService $apiService;

    public function __construct(BookstoreApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    // ==================== LOGIN ====================

    /**
     * Mostrar formulario de login
     */
    #[Route('/login', name: 'auth_login', methods: ['GET'])]
    public function loginForm(): Response
    {
        // Si ya está autenticado, redirigir al catálogo
        if ($this->apiService->isAuthenticated()) {
            return $this->redirectToRoute('catalog_index');
        }

        return $this->render('auth/login.html.twig');
    }

    /**
     * Procesar login
     */
    #[Route('/login', name: 'auth_login_submit', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        // Validar campos
        if (!$email || !$password) {
            $this->addFlash('error', 'Por favor, completa todos los campos.');
            return $this->redirectToRoute('auth_login');
        }

        // Llamar a la API
        $result = $this->apiService->login($email, $password);

        if ($result['success']) {
            $this->addFlash('success', '¡Bienvenido de nuevo!');

            // Redirigir a la página anterior o al catálogo
            $redirectUrl = $request->request->get('_target_path', $this->generateUrl('catalog_index'));
            return $this->redirect($redirectUrl);
        } else {
            $this->addFlash('error', $result['message'] ?? 'Credenciales incorrectas.');
            return $this->redirectToRoute('auth_login');
        }
    }

    // ==================== REGISTER ====================

    /**
     * Mostrar formulario de registro
     */
    #[Route('/register', name: 'auth_register', methods: ['GET'])]
    public function registerForm(): Response
    {
        // Si ya está autenticado, redirigir al catálogo
        if ($this->apiService->isAuthenticated()) {
            return $this->redirectToRoute('catalog_index');
        }

        return $this->render('auth/register.html.twig');
    }

    /**
     * Procesar registro
     */
    #[Route('/register', name: 'auth_register_submit', methods: ['POST'])]
    public function register(Request $request): Response
    {
        // Obtener datos del formulario
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');
        $nombre = $request->request->get('nombre');
        $apellidos = $request->request->get('apellidos');
        $celular = $request->request->get('celular');

        // Validaciones básicas
        if (!$email || !$password || !$nombre || !$apellidos) {
            $this->addFlash('error', 'Por favor, completa todos los campos obligatorios.');
            return $this->redirectToRoute('auth_register');
        }

        if ($password !== $confirmPassword) {
            $this->addFlash('error', 'Las contraseñas no coinciden.');
            return $this->redirectToRoute('auth_register');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'La contraseña debe tener al menos 8 caracteres.');
            return $this->redirectToRoute('auth_register');
        }

        // Preparar datos para la API
        $userData = [
            'email' => $email,
            'password' => $password,
            'nombre' => $nombre,
            'apellidos' => $apellidos,
        ];

        if ($celular) {
            $userData['celular'] = $celular;
        }

        // Llamar a la API
        $result = $this->apiService->register($userData);

        if ($result['success']) {
            $this->addFlash('success', '¡Registro exitoso! Por favor, inicia sesión.');
            return $this->redirectToRoute('auth_login');
        } else {
            $this->addFlash('error', $result['message'] ?? 'Error al registrar. Intenta nuevamente.');
            return $this->redirectToRoute('auth_register');
        }
    }

    // ==================== LOGOUT ====================

    /**
     * Cerrar sesión
     */
    #[Route('/logout', name: 'auth_logout')]
    public function logout(): Response
    {
        $this->apiService->logout();
        $this->addFlash('success', 'Sesión cerrada correctamente.');
        return $this->redirectToRoute('catalog_index');
    }
}
