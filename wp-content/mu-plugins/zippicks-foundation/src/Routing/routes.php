<?php
/**
 * Example Route Definitions
 * 
 * @package ZipPicks\Foundation\Routing
 * @since 1.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Contracts\Routing\RouterInterface;
use ZipPicks\Foundation\Routing\Controllers\TestController;

// Get the router instance
/** @var RouterInterface $router */
$router = foundation()->get('router');

// Basic routes
$router->get('/test', TestController::class)->middleware(['web'])->name('test');
$router->get('/test/index', [TestController::class, 'index'])->name('test.index');
$router->get('/test/{id}', TestController::class . '@show')->name('test.show');
$router->post('/test', TestController::class . '@store')->name('test.store');

// Route with closure
$router->get('/hello', function ($request) {
    return ['message' => 'Hello from custom route!'];
})->name('hello');

// Example using new Request system
$router->post('/search', function (\ZipPicks\Foundation\Contracts\Http\RequestInterface $request) {
    return response()->json([
        'query' => $request->input('q'),
        'filters' => $request->only(['category', 'location']),
        'page' => $request->query('page', 1),
        'is_ajax' => $request->isAjax(),
        'user_agent' => $request->userAgent()
    ]);
})->name('search');

// Protected routes group
$router->group(['prefix' => '/admin', 'middleware' => ['admin']], function (RouterInterface $router) {
    $router->get('/dashboard', function ($request) {
        return ['message' => 'Admin Dashboard', 'user' => wp_get_current_user()->display_name];
    })->name('admin.dashboard');
    
    $router->get('/settings', function ($request) {
        return ['message' => 'Admin Settings'];
    })->name('admin.settings');
});

// API routes group
$router->group(['prefix' => '/api/v1', 'middleware' => ['api']], function (RouterInterface $router) {
    // Resource routes
    $router->get('/businesses', function ($request) {
        return ['businesses' => ['Restaurant 1', 'Restaurant 2', 'Restaurant 3']];
    })->name('api.businesses.index');
    
    $router->post('/businesses', function ($request) {
        return ['message' => 'Business created', 'data' => $request->getBodyParams()];
    })->name('api.businesses.store');
    
    $router->get('/businesses/{id}', function ($request) {
        $id = $request->getContext()['route.id'] ?? 'unknown';
        return ['business' => ['id' => $id, 'name' => 'Business ' . $id]];
    })->name('api.businesses.show');
});

// Nested groups example
$router->group(['prefix' => '/app'], function (RouterInterface $router) {
    // Public app routes
    $router->get('/', function ($request) {
        return ['message' => 'App Home'];
    })->name('app.home');
    
    // Authenticated app routes
    $router->group(['middleware' => ['auth']], function (RouterInterface $router) {
        $router->get('/profile', function ($request) {
            return ['user' => wp_get_current_user()->display_name];
        })->name('app.profile');
        
        $router->post('/profile', function ($request) {
            return ['message' => 'Profile updated'];
        })->name('app.profile.update');
    });
});

// Route that matches any HTTP method
$router->any('/webhook', function ($request) {
    return [
        'message' => 'Webhook received',
        'method' => $request->getMethod(),
        'headers' => $request->getHeaders()
    ];
})->name('webhook');

// Route with multiple methods
$router->addRoute(['GET', 'POST'], '/contact', function ($request) {
    if ($request->getMethod() === 'GET') {
        return ['form' => 'Contact form HTML here'];
    }
    
    return ['message' => 'Contact form submitted', 'data' => $request->getBodyParams()];
})->name('contact');