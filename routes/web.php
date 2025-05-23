<?php

// Define public routes
$publicRoutes = [
    '/',
    '/login',
    '/register',
    '/contact-us',
    '/user-data',
    '/paginate-test',
];

// Define the access control map for routes
$accessMap = [
    // Shared (admin and user)
    '/logout' => ['customer', 'technician', 'admin'],
];

$router->setBasePath(''); // Set this if your app is in a subdirectory

// Define routes
// Home routes
$router->map('GET', '/', 'App\Controllers\HomeController#index', 'home');

$router->map('GET', '/services', 'App\Controllers\HomeController#service', 'service');
$router->map('GET', '/products', 'App\Controllers\HomeController#products', 'product');
$router->map('GET', '/about', 'App\Controllers\HomeController#about', 'about');
$router->map('GET', '/contact-us', 'App\Controllers\HomeController#contactUs', 'contact');
$router->map('POST', '/contact-us', 'App\Controllers\HomeController#contactUs', 'contact_post');
$router->map('GET', '/privacy-policy', 'App\Controllers\HomeController#privacy', 'privacy-policy');
$router->map('GET', '/terms-of-service', 'App\Controllers\HomeController#terms', 'terms-of-service');


// Auth routes
$router->map('GET', '/login', 'App\Controllers\AuthController#renderLogin', 'render_login');
$router->map('POST', '/login', 'App\Controllers\AuthController#loginAccount', 'login_post');
$router->map('GET', '/register', 'App\Controllers\AuthController#renderRegister', 'render_register');
$router->map('POST', '/register', 'App\Controllers\AuthController#registerAccount', 'register_post');
$router->map('GET', '/reset-password', 'App\Controllers\AuthController#renderResetPassword', 'reset_password');
$router->map('GET', '/logout', 'App\Controllers\AuthController#logout', 'logout');

// User routes
$router->map('GET', '/user/dashboard', 'App\Controllers\UserController#renderUserDashboard', 'render_user-dashboard');
$router->map('GET', '/user/services', 'App\Controllers\UserController#renderUserServices', 'render_user-products');
$router->map('GET', '/user/products', 'App\Controllers\UserController#renderUserProducts', 'render_user-services');
$router->map('POST', '/user/service/request', 'App\Controllers\ServiceRequestController#bookService', 'create-service request');
$router->map('GET', '/user/bookings', 'App\Controllers\ServiceRequestController#myBookings', 'user_bookings');
$router->map('POST', '/user/bookings/cancel/[i:id]', 'App\Controllers\ServiceRequestController#cancelBooking', 'user_cancel_booking');
$router->map('GET', '/user/my-orders', 'App\Controllers\UserController#renderMyOrders', 'render_my-orders');

// Service Request API endpoints for ServiceRequestsManager.js



// Admin routes
$router->map('GET', '/admin/service-requests', 'App\Controllers\AdminController#renderServiceRequest', 'render-service-request');
$router->map('GET', '/admin/inventory', 'App\Controllers\AdminController#renderInventory', 'render-inventory');
$router->map('GET', '/admin/add-product', 'App\Controllers\AdminController#renderAddProduct', 'render-add-product');
$router->map('GET', '/admin/reports', 'App\Controllers\AdminController#renderReports', 'render-reports');

// Service Request Management Routes
$router->map('GET', '/api/user/service-bookings', 'App\Controllers\ServiceRequestController#getUserServiceBookings', 'user_service_bookings_api');
$router->map('GET', '/api/user/service-bookings/[i:id]', 'App\Controllers\ServiceRequestController#getUserServiceBookingDetails', 'user_service_booking_details_api');

// Admin Service Request API Routes
$router->map('GET', '/api/admin/service-requests', 'App\Controllers\ServiceRequestController#getAdminServiceRequests', 'admin_service_requests_api');
$router->map('GET', '/api/admin/service-requests/[i:id]', 'App\Controllers\ServiceRequestController#getAdminServiceRequestDetails', 'admin_service_request_details_api');
$router->map('POST', '/api/admin/service-requests/update', 'App\Controllers\ServiceRequestController#updateServiceRequest', 'admin_update_service_request_api');
$router->map('POST', '/api/admin/service-requests/delete/[i:id]', 'App\Controllers\ServiceRequestController#deleteServiceRequest', 'admin_delete_service_request_api');

// API routes for technicians and service types
$router->map('GET', '/api/technicians', 'App\Controllers\ServiceRequestController#getTechnicians', 'technicians_api');

// User Management Routes 
$router->map('GET', '/admin/user-management', 'App\Controllers\UserManagementController#index', 'render-user-management');
$router->map('GET', '/api/users', 'App\Controllers\UserManagementController#getUsers', 'api_get_users');
$router->map('GET', '/api/users/data', 'App\Controllers\UserManagementController#getUsersData', 'api_get_users_data');
$router->map('GET', '/api/users/[i:id]', 'App\Controllers\UserManagementController#getUser', 'api_get_user');
$router->map('POST', '/api/users', 'App\Controllers\UserManagementController#createUser', 'api_create_user');
$router->map('PUT', '/api/users/[i:id]', 'App\Controllers\UserManagementController#updateUser', 'api_update_user');
$router->map('DELETE', '/api/users/[i:id]', 'App\Controllers\UserManagementController#deleteUser', 'api_delete_user');
$router->map('POST', '/api/users/reset-password/[i:id]', 'App\Controllers\UserManagementController#resetPassword', 'api_reset_password');
$router->map('GET', '/api/users/export', 'App\Controllers\UserManagementController#exportUsers', 'api_export_users');

// Inventory Management API Routes
$router->map('GET', '/api/inventory', 'App\Controllers\InventoryController#getAllInventory', 'get_all_inventory');
$router->map('GET', '/api/inventory/product/[i:id]', 'App\Controllers\InventoryController#getProductInventory', 'get_product_inventory');
$router->map('GET', '/api/inventory/warehouse/[i:id]', 'App\Controllers\InventoryController#getWarehouseInventory', 'get_warehouse_inventory');
$router->map('GET', '/api/inventory/low-stock', 'App\Controllers\InventoryController#getLowStockInventory', 'get_low_stock_inventory');
$router->map('GET', '/api/inventory/summary', 'App\Controllers\InventoryController#getInventorySummary', 'get_inventory_summary');
$router->map('POST', '/api/inventory/add-stock', 'App\Controllers\InventoryController#addStock', 'add_inventory_stock');
$router->map('POST', '/api/inventory/move-stock', 'App\Controllers\InventoryController#moveStock', 'move_inventory_stock');
$router->map('GET', '/api/inventory/stats', 'App\Controllers\InventoryController#getInventoryStats', 'get_inventory_stats');

// Product Management API Routes
$router->map('GET', '/api/products', 'App\Controllers\ProductController#getAllProducts', 'get_all_products');
$router->map('GET', '/api/products/[i:id]', 'App\Controllers\ProductController#getProduct', 'get_product');
$router->map('POST', '/api/products', 'App\Controllers\ProductController#createProduct', 'create_product');
$router->map('POST', '/api/products/[i:id]', 'App\Controllers\ProductController#updateProduct', 'update_product');
$router->map('POST', '/api/products/delete/[i:id]', 'App\Controllers\ProductController#deleteProduct', 'delete_product');
$router->map('GET', '/api/products/[i:id]/variants', 'App\Controllers\ProductController#getProductVariants', 'get_product_variants');
$router->map('GET', '/api/products/[i:id]/features', 'App\Controllers\ProductController#getProductFeatures', 'get_product_features');
$router->map('GET', '/api/products/[i:id]/specs', 'App\Controllers\ProductController#getProductSpecs', 'get_product_specs');

// Warehouse Management API Routes
$router->map('GET', '/api/warehouses', 'App\Controllers\WarehouseController#getAllWarehouses', 'get_all_warehouses');
$router->map('GET', '/api/warehouses/with-inventory', 'App\Controllers\WarehouseController#getWarehousesWithInventory', 'get_warehouses_with_inventory');
$router->map('GET', '/api/warehouses/[i:id]', 'App\Controllers\WarehouseController#getWarehouse', 'get_warehouse');
$router->map('POST', '/api/warehouses', 'App\Controllers\WarehouseController#createWarehouse', 'create_warehouse');
$router->map('POST', '/api/warehouses/[i:id]', 'App\Controllers\WarehouseController#updateWarehouse', 'update_warehouse');
$router->map('POST', '/api/warehouses/delete/[i:id]', 'App\Controllers\WarehouseController#deleteWarehouse', 'delete_warehouse');
$router->map('GET', '/api/warehouses/[i:id]/utilization', 'App\Controllers\WarehouseController#getWarehouseUtilization', 'get_warehouse_utilization');
$router->map('GET', '/api/warehouses/available-space', 'App\Controllers\WarehouseController#getWarehousesWithAvailableSpace', 'get_warehouses_with_available_space');
$router->map('GET', '/api/warehouses/product-distribution/[i:id]', 'App\Controllers\WarehouseController#getProductDistribution', 'get_product_distribution');
$router->map('GET', '/api/warehouses/search', 'App\Controllers\WarehouseController#searchWarehouses', 'search_warehouses');



