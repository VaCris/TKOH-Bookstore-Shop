<p align="center">
  <a href="https://studios-tkoh.azurewebsites.net/" target="blank"><img src="https://drive.google.com/uc?export=view&id=1TuT30CiBkinh85WuTvjKGKN47hCyCS0Z" width="300" alt="Studios TKOH! Logo" /></a>
</p>

# 📚 TKOH Bookstore Shop - E-commerce de Libros

**¡Hecho por Studios TKOH!**

Una plataforma de comercio electrónico moderna y robusta, construida con **Symfony**, diseñada para explorar, comprar y gestionar un catálogo de libros. Este proyecto se enfoca en ofrecer una experiencia completa de compra en línea con integración de servicios externos y una arquitectura limpia basada en PHP.

## 🚀 Módulos y Funcionalidades

Basado en los Controladores (`Controller`) y Servicios (`Service`), la aplicación ofrece las siguientes áreas principales:

### 1. Experiencia de Compra (E-commerce)
* **Catálogo y Detalle:** Navegación, búsqueda de libros y vista detallada con información enriquecida (posiblemente de Google Books).
* **Carrito de Compras (`Cart`):** Gestión completa de ítems, adición, eliminación y actualización de cantidades.
* **Proceso de Pago (`Checkout`):** Flujo de pago para finalizar la transacción y generar el pedido.
* **Conversión de Moneda:** Integración con API de tasas de cambio para mostrar precios en múltiples divisas.

### 2. Servicios e Integraciones
* **Autenticación (`Auth`):** Funcionalidades de registro e inicio de sesión de usuarios.
* **Integración de API Externa:** Conexión con **Google Books API** para obtener metadatos de libros y un servicio externo de tasas de cambio (`UpdateCurrencyRatesCommand`).
* **Servicio de Monedas (`CurrencyConverterService`):** Lógica para la conversión y actualización periódica de divisas.
* **Seguridad:** Implementación de protección CSRF y manejo seguro de sesiones.

## 🛠️ Stack Tecnológico

Este proyecto se apoya en tecnologías modernas de PHP y Frontend:

* **Backend:** PHP 8+ y **Symfony Framework** (Controladores, Servicios, Comandos).
* **Base de Datos:** Configuración lista para ser conectada (generalmente Doctrine ORM en Symfony).
* **Frontend:** JavaScript moderno, **Webpack Encore** (para gestión de assets) y **Symfony UX Turbo** para una experiencia rápida sin recargas.
* **Plantillas:** **Twig** templating engine.
* **Estilos:** Archivos CSS modulares (usando `assets/styles/app.css` como base).

## 📦 Configuración y Puesta en Marcha

Para ejecutar este proyecto, necesitarás PHP, Composer, y Node.js/npm para el manejo de dependencias de frontend.

### 1. Requisitos Previos

* PHP 8.2+
* Composer
* Node.js & npm (o yarn)

### 2. Instalación y Dependencias

1.  **Clonar el repositorio:**
    ```bash
    git clone [URL_DEL_REPOSITORIO]
    cd tkoh-bookstore-shop
    ```
2.  **Instalar dependencias de PHP:**
    ```bash
    composer install
    ```
3.  **Instalar dependencias de Frontend:**
    ```bash
    npm install
    ```

### 3. Configuración del Entorno

1.  **Crear el archivo de configuración local:**
    ```bash
    cp .env.dev .env.local
    ```
2.  **Actualizar variables de entorno:**
    Edita el archivo `.env.local` para configurar la base de datos (si aplica) y las claves de las APIs externas:
    ```dotenv
    # Asegúrate de configurar la clave para la API de tasas de cambio
    CURRENCY_API_KEY="TU_CLAVE_API_DE_MONEDAS" 
    ```

### 4. Ejecución

1.  **Compilar los assets de frontend (CSS/JS):**
    ```bash
    npm run dev
    # Usa 'npm run watch' para recarga automática durante el desarrollo.
    ```
2.  **Iniciar el servidor de desarrollo de Symfony:**
    ```bash
    symfony server:start
    ```
La aplicación estará disponible, por defecto, en `https://127.0.0.1:8000`.


<p align="center">
  <sub>🛠️ Desarrollado con 💙 por <strong>Studios TKOH</strong></sub><br>
  <a href="https://studios-tkoh.azurewebsites.net/" target="_blank">🌐 studios-tkoh.azurewebsites.net</a>
</p>
