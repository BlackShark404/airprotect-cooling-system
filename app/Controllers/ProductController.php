<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ProductFeatureModel;
use App\Models\ProductSpecModel;
use App\Models\ProductVariantModel;
use App\Models\ProductBookingModel;
use App\Models\InventoryModel;
use App\Models\WarehouseModel;

class ProductController extends BaseController
{
    private $productModel;
    private $productFeatureModel;
    private $productSpecModel;
    private $productVariantModel;
    private $productBookingModel;
    private $inventoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new ProductModel();
        $this->productFeatureModel = new ProductFeatureModel();
        $this->productSpecModel = new ProductSpecModel();
        $this->productVariantModel = new ProductVariantModel();
        $this->productBookingModel = new ProductBookingModel();
        $this->inventoryModel = new InventoryModel();
    }

    public function renderProductManagement()
    {
        $this->render('admin/product-management');
    }

    public function getAllProducts()
    {
        $products = $this->productModel->getAllProducts();
        $debug = [];
        
        // Process each product to add variant and inventory information
        foreach ($products as &$product) {
            // Get product ID, accounting for both uppercase and lowercase keys
            $productId = null;
            if (isset($product['PROD_ID'])) {
                $productId = $product['PROD_ID'];
            } elseif (isset($product['prod_id'])) {
                $productId = $product['prod_id'];
                // Also add uppercase version for consistency in later usage
                $product['PROD_ID'] = $productId;
            }
            
            // Skip this product if ID is missing
            if (!$productId) {
                continue;
            }
            
            // Get variants for this product
            $variants = $this->productVariantModel->getVariantsByProductId($productId);
            
            // Initialize array for variants with inventory info
            $variantsWithInventory = [];
            $variantIds = [];
            
            // Get all variant IDs for this product
            foreach ($variants as $variant) {
                // Account for both uppercase and lowercase keys
                $varId = null;
                if (isset($variant['VAR_ID'])) {
                    $varId = $variant['VAR_ID'];
                } elseif (isset($variant['var_id'])) {
                    $varId = $variant['var_id'];
                    // Add uppercase version for consistency
                    $variant['VAR_ID'] = $varId;
                }
                
                if ($varId) {
                    $variantIds[] = $varId;
                }
            }
            
            if (!empty($variantIds)) {
                // Get inventory data for all variants of this product
                $inventoryItems = $this->inventoryModel->getInventoryByVariantIds($variantIds);
                $variantInventoryMap = [];
                
                // Group inventory by variant ID and calculate total quantity
                foreach ($inventoryItems as $item) {
                    // Account for both uppercase and lowercase keys
                    $varId = isset($item['VAR_ID']) ? $item['VAR_ID'] : ($item['var_id'] ?? null);
                    if (!$varId) continue;
                    
                    if (!isset($variantInventoryMap[$varId])) {
                        $variantInventoryMap[$varId] = 0;
                    }
                    
                    // Account for both uppercase and lowercase keys
                    $quantity = isset($item['QUANTITY']) ? (int)$item['QUANTITY'] : (int)($item['quantity'] ?? 0);
                    $variantInventoryMap[$varId] += $quantity;
                }
                
                // Process all variants and add inventory quantities
                foreach ($variants as $variant) {
                    // Account for both uppercase and lowercase keys
                    $varId = isset($variant['VAR_ID']) ? $variant['VAR_ID'] : ($variant['var_id'] ?? null);
                    if (!$varId) continue;
                    
                    // Add inventory quantity (default to 0 if not in inventory)
                    $variant['INVENTORY_QUANTITY'] = $variantInventoryMap[$varId] ?? 0;
                    $variantsWithInventory[] = $variant;
                }
            }
            
            // Add all variants to the product, regardless of inventory
            $product['variants'] = $variantsWithInventory;
            
            // Flag if product has variants with inventory > 0
            $product['HAS_INVENTORY'] = count(array_filter($variantsWithInventory, function($v) {
                return ($v['INVENTORY_QUANTITY'] ?? 0) > 0;
            })) > 0;
            
            // Calculate total inventory count for the product
            $product['inventory_count'] = array_reduce($variantsWithInventory, function($total, $variant) {
                return $total + ($variant['INVENTORY_QUANTITY'] ?? 0);
            }, 0);
            
            $debug[] = [
                'product_id' => $productId,
                'variant_count' => count($variants),
                'variants_with_inventory' => count($variantsWithInventory),
                'has_inventory' => $product['HAS_INVENTORY'],
                'inventory_count' => $product['inventory_count']
            ];
        }
        
        // Show all products without filtering - the frontend will handle showing inventory availability
        $this->jsonSuccess($products);
    }

    public function getProduct($id)
    {
        $product = $this->productModel->getProductWithDetails($id);
        
        if (!$product) {
            $this->jsonError('Product not found', 404);
            return;
        }

        // Get inventory information
        $product['inventory'] = $this->inventoryModel->getProductInventory($id);
        
        // Add inventory quantity to each variant
        if (isset($product['variants']) && is_array($product['variants'])) {
            $variantIds = [];
            foreach ($product['variants'] as &$variant) {
                // Get variant ID (handling both uppercase and lowercase keys)
                $varId = isset($variant['VAR_ID']) ? $variant['VAR_ID'] : ($variant['var_id'] ?? null);
                if ($varId) {
                    $variantIds[] = $varId;
                }
            }
            
            if (!empty($variantIds)) {
                // Get inventory data for all variants
                $inventoryItems = $this->inventoryModel->getInventoryByVariantIds($variantIds);
                $variantInventoryMap = [];
                
                // Group inventory by variant ID and calculate total quantity
                foreach ($inventoryItems as $item) {
                    // Handle both uppercase and lowercase keys
                    $varId = isset($item['VAR_ID']) ? $item['VAR_ID'] : ($item['var_id'] ?? null);
                    if (!$varId) continue;
                    
                    if (!isset($variantInventoryMap[$varId])) {
                        $variantInventoryMap[$varId] = 0;
                    }
                    
                    // Handle both uppercase and lowercase 'quantity' keys
                    $quantity = isset($item['QUANTITY']) ? (int)$item['QUANTITY'] : (int)($item['quantity'] ?? 0);
                    $variantInventoryMap[$varId] += $quantity;
                }
                
                // Add inventory quantities to each variant
                foreach ($product['variants'] as &$variant) {
                    $varId = isset($variant['VAR_ID']) ? $variant['VAR_ID'] : ($variant['var_id'] ?? null);
                    if ($varId) {
                        $variant['INVENTORY_QUANTITY'] = $variantInventoryMap[$varId] ?? 0;
                    } else {
                        $variant['INVENTORY_QUANTITY'] = 0;
                    }
                }
            }
        }
        
        $this->jsonSuccess($product);
    }

    public function createProduct()
    {
        if (!$this->isPost()) {
            $this->renderError('Bad Request: Expected POST', 400);
            return;
        }
        
        $payload = [];
        
        // 1. Get and decode the 'product' JSON string
        $productJson = $this->request('product');
        if (empty($productJson)) {
            $this->jsonError('Missing product data field.', 400);
            return;
        }
        $payload['product'] = json_decode($productJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError('Invalid product JSON data: ' . json_last_error_msg(), 400);
            return;
        }

        // Debug: Log the received payload
        error_log("Product JSON: " . $productJson);
        
        // Normalize field names to uppercase for database consistency
        if (isset($payload['product']['prod_name'])) {
            $payload['product']['PROD_NAME'] = $payload['product']['prod_name'];
            unset($payload['product']['prod_name']);
        }
        
        if (isset($payload['product']['prod_description'])) {
            $payload['product']['PROD_DESCRIPTION'] = $payload['product']['prod_description'];
            unset($payload['product']['prod_description']);
        }

        // 2. Handle 'product_image' file upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $uploadedImage = $_FILES['product_image'];
            $imageName = basename($uploadedImage['name']);
            $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($imageExt, $allowedExts)) {
                $this->jsonError('Invalid image file type. Allowed types: ' . implode(', ', $allowedExts), 400);
                return;
            }

            // Define upload path - relative to the public directory
            // Assumes your web root is 'public' and this controller is accessed via index.php in public
            $uploadDir = 'uploads/products/'; 
            $absoluteUploadPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $uploadDir;

            if (!is_dir($absoluteUploadPath)) {
                if (!mkdir($absoluteUploadPath, 0777, true)) {
                    error_log("Failed to create directory: " . $absoluteUploadPath);
                    $this->jsonError('Failed to create image upload directory.', 500);
                    return;
                }
            }
            
            // Generate a unique name to prevent overwrites
            $uniqueImageName = uniqid('prod_', true) . '.' . $imageExt;
            $targetPath = $absoluteUploadPath . $uniqueImageName;

            if (move_uploaded_file($uploadedImage['tmp_name'], $targetPath)) {
                $payload['product']['PROD_IMAGE'] = $uploadDir . $uniqueImageName; // Store relative path for DB
            } else {
                error_log("Failed to move uploaded file to: " . $targetPath);
                $this->jsonError('Failed to save product image.', 500);
                return;
            }
        } else {
            // If PROD_IMAGE is already set in the JSON (e.g., as a URL or base64 for update, or if optional)
            // Or if it's truly missing for a new product where it's required by DB.
            // The validation below will catch it if it's still empty and required.
            if (!isset($payload['product']['PROD_IMAGE'])) {
                 $payload['product']['PROD_IMAGE'] = null; 
            }
        }

        // 3. Get and decode 'features', 'specs', 'variants' JSON strings
        $featuresJson = $this->request('features');
        error_log("Features JSON: " . $featuresJson);
        $payload['features'] = $featuresJson ? json_decode($featuresJson, true) : [];
        if ($featuresJson && json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError('Invalid features JSON data: ' . json_last_error_msg(), 400);
            return;
        }

        $specsJson = $this->request('specs');
        error_log("Specs JSON: " . $specsJson);
        $payload['specs'] = $specsJson ? json_decode($specsJson, true) : [];
        if ($specsJson && json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError('Invalid specs JSON data: ' . json_last_error_msg(), 400);
            return;
        }

        $variantsJson = $this->request('variants');
        error_log("Variants JSON: " . $variantsJson);
        $payload['variants'] = $variantsJson ? json_decode($variantsJson, true) : [];
        if ($variantsJson && json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError('Invalid variants JSON data: ' . json_last_error_msg(), 400);
            return;
        }
        
        // Validate required product fields with clear error message
        $missingFields = [];
        
        if (empty($payload['product']['PROD_NAME'])) {
            $missingFields[] = 'Name';
        }
        
        if (empty($payload['product']['PROD_IMAGE'])) {
            $missingFields[] = 'Image';
        }
        
        if (!empty($missingFields)) {
            $this->jsonError('Missing required product fields: ' . implode(', ', $missingFields) . '.', 400);
            return;
        }
        
        // Start transaction
        $this->productModel->beginTransaction();
        
        try {
            // Create product
            $productId = $this->productModel->createProduct($payload['product']);
            
            if (!$productId) {
                throw new \Exception("Failed to create product entry in database");
            }
            
            // Create features if provided
            if (!empty($payload['features']) && is_array($payload['features'])) {
                foreach ($payload['features'] as $feature) {
                    $featureData = [];
                    $featureData['PROD_ID'] = $productId;
                    
                    // Extract feature name from the feature data
                    if (isset($feature['FEATURE_NAME'])) {
                        $featureData['FEATURE_NAME'] = $feature['FEATURE_NAME'];
                    } else if (isset($feature['feature_name'])) {
                        $featureData['FEATURE_NAME'] = $feature['feature_name'];
                    } else if (!is_array($feature)) {
                        $featureData['FEATURE_NAME'] = $feature;
                    }
                    
                    if (empty($featureData['FEATURE_NAME'])) {
                        error_log("Skipping feature due to missing name: " . json_encode($feature));
                        continue; // Skip empty features
                    }
                    
                    // Debug: Log the feature being added
                    error_log("Adding feature: " . json_encode($featureData));
                    
                    $this->productFeatureModel->createFeature($featureData);
                }
            }
            
            // Create specs if provided
            if (!empty($payload['specs']) && is_array($payload['specs'])) {
                foreach ($payload['specs'] as $spec) {
                    $specData = [];
                    $specData['PROD_ID'] = $productId;
                    
                    // Extract spec name and value from the spec data
                    if (isset($spec['SPEC_NAME'])) {
                        $specData['SPEC_NAME'] = $spec['SPEC_NAME'];
                    } else if (isset($spec['spec_name'])) {
                        $specData['SPEC_NAME'] = $spec['spec_name'];
                    }
                    
                    if (isset($spec['SPEC_VALUE'])) {
                        $specData['SPEC_VALUE'] = $spec['SPEC_VALUE'];
                    } else if (isset($spec['spec_value'])) {
                        $specData['SPEC_VALUE'] = $spec['spec_value'];
                    }
                    
                    if (empty($specData['SPEC_NAME']) || !isset($specData['SPEC_VALUE'])) {
                        error_log("Skipping spec due to missing name or value: " . json_encode($spec));
                        continue; // Skip incomplete specs
                    }
                    
                    // Debug: Log the spec being added
                    error_log("Adding spec: " . json_encode($specData));
                    
                    $this->productSpecModel->createSpec($specData);
                }
            }
            
            // Create variants if provided
            if (!empty($payload['variants']) && is_array($payload['variants'])) {
                foreach ($payload['variants'] as $variant) {
                    $variantData = [];
                    $variantData['PROD_ID'] = $productId;
                    
                    // Field mappings for variants
                    $fieldMappings = [
                        'VAR_CAPACITY' => ['var_capacity', 'VAR_CAPACITY'],
                        'VAR_SRP_PRICE' => ['var_srp_price', 'VAR_SRP_PRICE'],
                        'VAR_DISCOUNT_FREE_INSTALL_PCT' => ['var_discount_free_install_pct', 'VAR_DISCOUNT_FREE_INSTALL_PCT'],
                        'VAR_DISCOUNT_WITH_INSTALL_PCT' => ['var_discount_with_install_pct', 'VAR_DISCOUNT_WITH_INSTALL_PCT'],
                        'VAR_INSTALLATION_FEE' => ['var_installation_fee', 'VAR_INSTALLATION_FEE'],
                        'VAR_POWER_CONSUMPTION' => ['var_power_consumption', 'VAR_POWER_CONSUMPTION']
                    ];
                    
                    // Extract all fields for the variant
                    foreach ($fieldMappings as $targetField => $sourceFields) {
                        foreach ($sourceFields as $sourceField) {
                            if (isset($variant[$sourceField])) {
                                $variantData[$targetField] = $variant[$sourceField];
                                break;
                            }
                        }
                    }
                    
                    // Check required fields
                    if (empty($variantData['VAR_CAPACITY']) || empty($variantData['VAR_SRP_PRICE'])) {
                        error_log("Skipping variant due to missing capacity or SRP price: " . json_encode($variant));
                        continue;
                    }
                    
                    // Debug: Log the variant being added
                    error_log("Adding variant: " . json_encode($variantData));
                    
                    $this->productVariantModel->createVariant($variantData);
                }
            }
            
            // Add inventory if provided (assuming it comes similarly, or within 'product' data)
            // If 'inventory' is also a separate JSON string:
            // $inventoryJson = $this->request('inventory');
            // $payload['inventory'] = $inventoryJson ? json_decode($inventoryJson, true) : [];
            // if ($inventoryJson && json_last_error() !== JSON_ERROR_NONE) { /* error handling */ }

            if (!empty($payload['inventory']) && is_array($payload['inventory'])) { // Assuming inventory might be part of the 'product' field for now or handled separately
                foreach ($payload['inventory'] as $inventoryItem) {
                    $inventoryItem['PROD_ID'] = $productId;
                    // Add validation for required inventory fields
                    $this->inventoryModel->createInventory($inventoryItem);
                }
            }
            
            // Commit the transaction
            $this->productModel->commit();
            
            $this->jsonSuccess(['product_id' => $productId], 'Product created successfully');
            
        } catch (\Exception $e) {
            $this->productModel->rollback();
            error_log("Error creating product: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            // Provide a more generic error to the client for security
            $this->jsonError('An unexpected error occurred while creating the product. Please check server logs.', 500);
        }
    }

    public function updateProduct($id)
    {
        if (!$this->isAjax() || !$this->isPost()) {
            $this->renderError('Bad Request', 400);
            return;
        }
        
        // Try to get data from JSON input, fallback to form data for multipart/form-data
        $data = $this->getJsonInput();
        if (empty($data) && !empty($_POST['product'])) {
            // Handle multipart/form-data
            $data = [
                'product' => json_decode($_POST['product'], true),
                'features' => !empty($_POST['features']) ? json_decode($_POST['features'], true) : [],
                'specs' => !empty($_POST['specs']) ? json_decode($_POST['specs'], true) : [],
                'variants' => !empty($_POST['variants']) ? json_decode($_POST['variants'], true) : [],
            ];
            
            // Handle image upload if present
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadedImage = $_FILES['product_image'];
                $imageName = basename($uploadedImage['name']);
                $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                
                // Generate a unique name to prevent overwrites
                $uniqueImageName = uniqid('prod_', true) . '.' . $imageExt;
                $uploadDir = 'uploads/products/';
                $absoluteUploadPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $uploadDir;
                
                if (!is_dir($absoluteUploadPath)) {
                    if (!mkdir($absoluteUploadPath, 0777, true)) {
                        error_log("Failed to create directory: " . $absoluteUploadPath);
                        $this->jsonError('Failed to create image upload directory.', 500);
                        return;
                    }
                }
                
                $targetPath = $absoluteUploadPath . $uniqueImageName;
                
                if (move_uploaded_file($uploadedImage['tmp_name'], $targetPath)) {
                    $data['product']['prod_image'] = $uploadDir . $uniqueImageName;
                } else {
                    error_log("Failed to move uploaded file to: " . $targetPath);
                    $this->jsonError('Failed to save product image.', 500);
                    return;
                }
            }
        }
        
        // Check if product exists
        $existingProduct = $this->productModel->getProductById($id);
        if (!$existingProduct) {
            $this->jsonError('Product not found', 404);
            return;
        }
        
        // Start transaction
        $this->productModel->beginTransaction();
        
        try {
            // Update product
            if (!empty($data['product'])) {
                $productData = $data['product'];
                
                // Normalize product data keys to uppercase
                if (isset($productData['prod_name'])) {
                    $productData['PROD_NAME'] = $productData['prod_name'];
                    unset($productData['prod_name']);
                }
                if (isset($productData['prod_description'])) {
                    $productData['PROD_DESCRIPTION'] = $productData['prod_description'];
                    unset($productData['prod_description']);
                }
                if (isset($productData['prod_image'])) {
                    $productData['PROD_IMAGE'] = $productData['prod_image'];
                    unset($productData['prod_image']);
                }
                
                $this->productModel->updateProduct($id, $productData);
            }
            
            // Update features
            if (isset($data['features'])) {
                // Delete existing features and add new ones
                $this->productFeatureModel->deleteFeaturesByProductId($id);
                
                if (!empty($data['features']) && is_array($data['features'])) {
                    foreach ($data['features'] as $feature) {
                        // Normalize feature data keys to uppercase
                        $normalizedFeature = [];
                        $normalizedFeature['PROD_ID'] = $id;
                        
                        if (isset($feature['feature_name'])) {
                            $normalizedFeature['FEATURE_NAME'] = $feature['feature_name'];
                        } elseif (isset($feature['FEATURE_NAME'])) {
                            $normalizedFeature['FEATURE_NAME'] = $feature['FEATURE_NAME'];
                        }
                        
                        if (isset($feature['feature_id'])) {
                            $normalizedFeature['FEATURE_ID'] = $feature['feature_id'];
                        } elseif (isset($feature['FEATURE_ID'])) {
                            $normalizedFeature['FEATURE_ID'] = $feature['FEATURE_ID'];
                        }
                        
                        $this->productFeatureModel->createFeature($normalizedFeature);
                    }
                }
            }
            
            // Update specs
            if (isset($data['specs'])) {
                // Delete existing specs and add new ones
                $this->productSpecModel->deleteSpecsByProductId($id);
                
                if (!empty($data['specs']) && is_array($data['specs'])) {
                    foreach ($data['specs'] as $spec) {
                        // Normalize spec data keys to uppercase
                        $normalizedSpec = [];
                        $normalizedSpec['PROD_ID'] = $id;
                        
                        if (isset($spec['spec_name'])) {
                            $normalizedSpec['SPEC_NAME'] = $spec['spec_name'];
                        } elseif (isset($spec['SPEC_NAME'])) {
                            $normalizedSpec['SPEC_NAME'] = $spec['SPEC_NAME'];
                        }
                        
                        if (isset($spec['spec_value'])) {
                            $normalizedSpec['SPEC_VALUE'] = $spec['spec_value'];
                        } elseif (isset($spec['SPEC_VALUE'])) {
                            $normalizedSpec['SPEC_VALUE'] = $spec['SPEC_VALUE'];
                        }
                        
                        if (isset($spec['spec_id'])) {
                            $normalizedSpec['SPEC_ID'] = $spec['spec_id'];
                        } elseif (isset($spec['SPEC_ID'])) {
                            $normalizedSpec['SPEC_ID'] = $spec['SPEC_ID'];
                        }
                        
                        $this->productSpecModel->createSpec($normalizedSpec);
                    }
                }
            }
            
            // Update variants
            if (isset($data['variants'])) {
                // Get existing variants before deletion
                $existingVariants = $this->productVariantModel->getVariantsByProductId($id);
                $existingVariantIds = [];
                $newToOldVariantMap = [];
                
                // Create a map of existing variants for reference
                foreach ($existingVariants as $variant) {
                    $variantId = isset($variant['VAR_ID']) ? $variant['VAR_ID'] : $variant['var_id'];
                    $capacity = isset($variant['VAR_CAPACITY']) ? $variant['VAR_CAPACITY'] : $variant['var_capacity'];
                    $existingVariantIds[$capacity] = $variantId;
                }
                
                // Delete existing variants
                $this->productVariantModel->deleteVariantsByProductId($id);
                
                if (!empty($data['variants']) && is_array($data['variants'])) {
                    foreach ($data['variants'] as $variant) {
                        // Normalize variant data keys to uppercase
                        $normalizedVariant = [];
                        $normalizedVariant['PROD_ID'] = $id;
                        
                        // Map lowercase to uppercase keys
                        $keyMap = [
                            'var_id' => 'VAR_ID',
                            'var_capacity' => 'VAR_CAPACITY',
                            'var_srp_price' => 'VAR_SRP_PRICE',
                            'var_discount_free_install_pct' => 'VAR_DISCOUNT_FREE_INSTALL_PCT',
                            'var_discount_with_install_pct' => 'VAR_DISCOUNT_WITH_INSTALL_PCT',
                            'var_installation_fee' => 'VAR_INSTALLATION_FEE',
                            'var_power_consumption' => 'VAR_POWER_CONSUMPTION'
                        ];
                        
                        foreach ($keyMap as $lowerKey => $upperKey) {
                            if (isset($variant[$lowerKey])) {
                                $normalizedVariant[$upperKey] = $variant[$lowerKey];
                            } elseif (isset($variant[$upperKey])) {
                                $normalizedVariant[$upperKey] = $variant[$upperKey];
                            }
                        }
                        
                        // Get the capacity value for mapping
                        $capacity = $normalizedVariant['VAR_CAPACITY'];
                        
                        // Create new variant and get its ID
                        $newVariantId = $this->productVariantModel->createVariant($normalizedVariant);
                        
                        // If this capacity existed before, map the new ID to the old ID
                        if (isset($existingVariantIds[$capacity])) {
                            $newToOldVariantMap[$newVariantId] = $existingVariantIds[$capacity];
                        }
                    }
                    
                    // Now update inventory references with the new variant IDs
                    foreach ($newToOldVariantMap as $newVariantId => $oldVariantId) {
                        $this->inventoryModel->updateVariantReferences($oldVariantId, $newVariantId);
                    }
                }
            }
            
            // Commit the transaction
            $this->productModel->commit();
            
            $this->jsonSuccess(['product_id' => $id], 'Product updated successfully');
            
        } catch (\Exception $e) {
            $this->productModel->rollback();
            error_log("Error updating product: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->jsonError('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    public function deleteProduct($id)
    {
        if (!$this->isAjax() || !$this->isPost()) {
            $this->renderError('Bad Request', 400);
            return;
        }
        
        // Check if product exists
        $existingProduct = $this->productModel->getProductById($id);
        if (!$existingProduct) {
            $this->jsonError('Product not found', 404);
            return;
        }
        
        // Delete the product (soft delete)
        $result = $this->productModel->deleteProduct($id);
        
        if ($result) {
            $this->jsonSuccess(null, 'Product deleted successfully');
        } else {
            $this->jsonError('Failed to delete product', 500);
        }
    }

    public function getProductFeatures($id)
    {
        if (!$this->isAjax()) {
            $this->renderError('Bad Request', 400);
            return;
        }

        $features = $this->productFeatureModel->getFeaturesByProductId($id);
        $this->jsonSuccess($features);
    }

    public function getProductSpecs($id)
    {
        if (!$this->isAjax()) {
            $this->renderError('Bad Request', 400);
            return;
        }

        $specs = $this->productSpecModel->getSpecsByProductId($id);
        $this->jsonSuccess($specs);
    }

    public function getProductVariants($id)
    {
        if (!$this->isAjax()) {
            $this->renderError('Bad Request', 400);
            return;
        }

        $variants = $this->productVariantModel->getVariantsByProductId($id);
        $this->jsonSuccess($variants);
    }

    public function getProductSummary()
    {
        $summary = $this->productModel->getProductSummary();
        $this->jsonSuccess($summary);
    }
    
    /**
     * Handle product booking creation
     */
    public function createProductBooking()
    {
        // Get the booking data from the request
        $data = $this->getJsonInput();
        if (empty($data)) {
            $data = $_POST; // Try to get from regular form data if JSON is empty
        }
        
        // Get the current user ID from the session
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->jsonError('You must be logged in to place a booking', 401);
            return;
        }
        
        // Check if we have the necessary booking data
        $requiredFields = [
            'PB_VARIANT_ID' => 'Variant',
            'PB_QUANTITY' => 'Quantity',
            'PB_PREFERRED_DATE' => 'Preferred Date',
            'PB_PREFERRED_TIME' => 'Preferred Time',
            'PB_ADDRESS' => 'Address'
        ];
        
        $missingFields = [];
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $missingFields[] = $label;
            }
        }
        
        if (!empty($missingFields)) {
            $this->jsonError('Missing required fields: ' . implode(', ', $missingFields), 400);
            return;
        }
        
        // Ensure quantity is valid
        $quantity = intval($data['PB_QUANTITY']);
        if ($quantity <= 0) {
            $this->jsonError('Quantity must be greater than zero', 400);
            return;
        }
        
        // Verify that the variant exists
        $variantId = intval($data['PB_VARIANT_ID']);
        $variant = $this->productVariantModel->getVariantById($variantId);
        if (!$variant) {
            $this->jsonError('Selected product variant not found', 404);
            return;
        }
        
        try {
            // Start transaction to ensure booking creation is atomic
            $this->productBookingModel->beginTransaction();
            error_log("Started transaction for booking creation");
            
            // Set default price type to free_install (admin will update later)
            $priceType = 'free_install';
            
            // Set unit price to 0.00 - admin will update based on customer location and requirements
            $unitPrice = 0.00;
            
            // Create a complete booking data structure
            $bookingData = [
                'PB_CUSTOMER_ID' => $userId,
                'PB_VARIANT_ID' => $variantId,
                'PB_QUANTITY' => $quantity,
                'PB_UNIT_PRICE' => $unitPrice,
                'PB_PRICE_TYPE' => $priceType,
                'PB_PREFERRED_DATE' => $data['PB_PREFERRED_DATE'],
                'PB_PREFERRED_TIME' => $data['PB_PREFERRED_TIME'],
                'PB_ADDRESS' => $data['PB_ADDRESS']
            ];
            
            // Handle description field - use default text if empty
            $bookingData['PB_DESCRIPTION'] = !empty($data['PB_DESCRIPTION']) ? $data['PB_DESCRIPTION'] : 'No additional instructions provided';
            
            error_log("Creating booking with data: " . json_encode($bookingData));
            
            // Create the booking in the database
            $bookingId = $this->productBookingModel->createBooking($bookingData);
            
            if (!$bookingId) {
                error_log("Failed to create booking record in database");
                $this->productBookingModel->rollback();
                $this->jsonError('Failed to create booking. Please try again.', 500);
                return;
            }
            
            error_log("Booking created with ID: " . $bookingId);
            
            // All operations successful, commit the transaction
            $commitResult = $this->productBookingModel->commit();
            error_log("Transaction commit result: " . ($commitResult ? 'Success' : 'Failed'));
            
            // Return success response with the booking ID
            $this->jsonSuccess([
                'PB_ID' => $bookingId,
                'message' => 'Your booking has been received and is being processed.'
            ], 'Booking created successfully');
            
        } catch (\PDOException $e) {
            // Handle database errors
            $this->productBookingModel->rollback();
            error_log("Database error creating booking: " . $e->getMessage());
            $this->jsonError('A database error occurred while processing your booking. Please try again.', 500);
        } catch (\Exception $e) {
            // Handle other errors
            $this->productBookingModel->rollback();
            error_log("Error creating booking: " . $e->getMessage());
            $this->jsonError('An error occurred while processing your booking. Please try again.', 500);
        }
    }

    /**
     * Get all product bookings for the current user
     */
    public function getUserProductBookings()
    {
        // Get the current user ID from the session
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->jsonError('You must be logged in to view your bookings', 401);
            return;
        }
        
        // Get all bookings for this customer
        $bookings = $this->productBookingModel->getBookingsByCustomerId($userId);
        
        // Return the bookings as JSON
        $this->jsonSuccess($bookings);
    }
    
    /**
     * Get details for a specific product booking
     */
    public function getUserProductBookingDetails($id)
    {
        // Get the current user ID from the session
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->jsonError('You must be logged in to view booking details', 401);
            return;
        }
        
        // Get the booking details
        $booking = $this->productBookingModel->getBookingById($id);
        
        // Check if the booking exists and belongs to this user
        if (!$booking) {
            $this->jsonError('Booking not found', 404);
            return;
        }
        
        // Check if the booking belongs to the current user
        $customerId = $booking['pb_customer_id'] ?? $booking['PB_CUSTOMER_ID'] ?? null;
        if (!$customerId || $customerId != $userId) {
            $this->jsonError('You do not have permission to view this booking', 403);
            return;
        }
        
        // Get technicians assigned to this booking
        $booking['technicians'] = $this->productBookingModel->getAssignedTechnicians($id);
        
        $this->jsonSuccess($booking);
    }

    /**
     * Get all product bookings for admin
     */
    public function getAdminProductBookings()
    {
        // Check if user is admin
        if (!$this->isAdmin()) {
            $this->jsonError('Unauthorized access', 401);
            return;
        }
        
        // Get filter parameters
        $filters = [];
        
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
            $filters['product_id'] = $_GET['product_id'];
        }
        
        if (isset($_GET['date_range']) && !empty($_GET['date_range'])) {
            $filters['date_range'] = $_GET['date_range'];
        }
        
        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
            $filters['technician_id'] = $_GET['technician_id'];
        }
        
        if (isset($_GET['has_technician'])) {
            $filters['has_technician'] = filter_var($_GET['has_technician'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Get all bookings with filters
        $bookings = $this->productBookingModel->getFilteredBookings($filters);
        
        // Enhance the response with additional information
        if ($bookings) {
            foreach ($bookings as &$booking) {
                // Convert uppercase keys to lowercase for consistency
                if (isset($booking['CUSTOMER_EMAIL'])) {
                    $booking['customer_email'] = $booking['CUSTOMER_EMAIL'];
                }
                if (isset($booking['CUSTOMER_PHONE'])) {
                    $booking['customer_phone'] = $booking['CUSTOMER_PHONE'];
                }
                if (isset($booking['CUSTOMER_PROFILE_URL'])) {
                    $booking['customer_profile_url'] = $booking['CUSTOMER_PROFILE_URL'];
                } 

                // Get assigned technicians for each booking
                $booking['technicians'] = $this->productBookingModel->getAssignedTechnicians($booking['pb_id'] ?? $booking['PB_ID'] ?? null);
                
                // Add profile images to technicians
                foreach ($booking['technicians'] as &$tech) {
                    $techInfo = $this->getUserInfo($tech['id']);
                    if ($techInfo) {
                        // Use profile_url from database if available
                        if (!empty($techInfo['UA_PROFILE_URL'])) {
                            $tech['profile_url'] = $techInfo['UA_PROFILE_URL'];
                        }
                        
                        $tech['email'] = $techInfo['UA_EMAIL'] ?? '';
                        $tech['phone'] = $techInfo['UA_PHONE_NUMBER'] ?? '';
                    }
                }
            }
        }
        
        // Return the bookings as JSON
        $this->jsonSuccess($bookings);
    }
    
    /**
     * Get details for a specific product booking (admin)
     */
    public function getAdminProductBookingDetails($id)
    {
        // Check if user is admin
        if (!$this->isAdmin()) {
            $this->jsonError('Unauthorized access', 401);
            return;
        }
        
        // Get the booking details
        $booking = $this->productBookingModel->getBookingById($id);
        
        if (!$booking) {
            $this->jsonError('Booking not found', 404);
            return;
        }
        
        // Debug log to see what's in the booking data
        error_log("Product booking data: " . json_encode($booking));
        
        // Convert uppercase keys to lowercase for consistency
        if (isset($booking['CUSTOMER_EMAIL'])) {
            $booking['customer_email'] = $booking['CUSTOMER_EMAIL'];
        }
        if (isset($booking['CUSTOMER_PHONE'])) {
            $booking['customer_phone'] = $booking['CUSTOMER_PHONE'];
        }
        if (isset($booking['CUSTOMER_PROFILE_URL'])) {
            $booking['customer_profile_url'] = $booking['CUSTOMER_PROFILE_URL'];
        }
        
        // Get assigned technicians for this booking
        $technicians = $this->productBookingModel->getAssignedTechnicians($id);
        
        // Debug log for technicians
        error_log("Product booking technicians (raw): " . json_encode($technicians));
        
        // Process technician data to ensure email and phone are included
        $processedTechnicians = [];
        foreach ($technicians as $tech) {
            $techData = [
                'id' => $tech['id'],
                'name' => $tech['name'],
                'email' => isset($tech['email']) ? $tech['email'] : '',
                'phone' => isset($tech['phone']) ? $tech['phone'] : '',
                'profile_url' => $tech['profile_url'] ?? '/assets/images/user-profile/default-profile.png',
                'status' => $tech['status'] ?? 'assigned',
                'notes' => $tech['notes'] ?? ''
            ];
            $processedTechnicians[] = $techData;
        }
        
        // Debug log for processed technicians
        error_log("Product booking technicians (processed): " . json_encode($processedTechnicians));
        
        $booking['technicians'] = $processedTechnicians;
        
        // Return the booking details as JSON
        $this->jsonSuccess($booking);
    }
    
    /**
     * Update a product booking (admin)
     */
    public function updateProductBooking()
    {
        // Check if user is admin
        if (!$this->isAdmin()) {
            $this->jsonError('Unauthorized access', 401);
            return;
        }
        
        // Get the booking data from the request
        $data = $this->getJsonInput();
        
        if (empty($data) || empty($data['bookingId'])) {
            $this->jsonError('Missing required booking information', 400);
            return;
        }
        
        $bookingId = $data['bookingId'];
        
        // Check if booking exists
        $booking = $this->productBookingModel->getBookingById($bookingId);
        if (!$booking) {
            $this->jsonError('Booking not found', 404);
            return;
        }
        
        // Ensure inventoryModel is initialized
        if (!isset($this->inventoryModel)) {
            $this->inventoryModel = new \App\Models\InventoryModel();
            error_log("InventoryModel was not initialized, created a new instance");
        }
        
        // Start transaction for safe updates
        $this->productBookingModel->beginTransaction();
        
        try {
            // Get current booking details
            $currentStatus = isset($booking['PB_STATUS']) ? $booking['PB_STATUS'] : (isset($booking['pb_status']) ? $booking['pb_status'] : '');
            $currentQuantity = isset($booking['PB_QUANTITY']) ? intval($booking['PB_QUANTITY']) : intval($booking['pb_quantity'] ?? 0);
            $variantId = isset($booking['PB_VARIANT_ID']) ? intval($booking['PB_VARIANT_ID']) : intval($booking['pb_variant_id'] ?? 0);
            
            // Check if quantity is changing
            $newQuantity = isset($data['quantity']) ? intval($data['quantity']) : $currentQuantity;
            $quantityDifference = $newQuantity - $currentQuantity;
            
            // Check if status is changing to confirmed
            $statusChangingToConfirmed = false;
            $newStatus = !empty($data['status']) ? $data['status'] : $currentStatus;
            
            if ($newStatus === 'confirmed' && $currentStatus !== 'confirmed') {
                $statusChangingToConfirmed = true;
                error_log("Status changing from {$currentStatus} to confirmed");
                
                // If status is changing to confirmed, need to check inventory
                if (!$variantId || $newQuantity <= 0) {
                    $this->productBookingModel->rollback();
                    $this->jsonError('Invalid booking data: missing variant or invalid quantity', 500);
                    return;
                }
                
                // Check if there's enough inventory and reduce it
                $inventoryReduced = $this->inventoryModel->reduceStock($variantId, $newQuantity);
                error_log("Inventory reduction result for confirmation: " . ($inventoryReduced ? "Success" : "Failed"));
                
                if (!$inventoryReduced) {
                    $this->productBookingModel->rollback();
                    $this->jsonError('Insufficient inventory for this booking. Cannot confirm.', 400);
                    return;
                }
                
                error_log("Inventory successfully reduced for variant {$variantId}, quantity {$newQuantity} on booking confirmation");
            }
            // Handle quantity change for already confirmed bookings
            else if ($currentStatus === 'confirmed' && $quantityDifference != 0 && !$statusChangingToConfirmed) {
                error_log("Quantity changing from {$currentQuantity} to {$newQuantity} (difference: {$quantityDifference})");
                
                if ($quantityDifference > 0) {
                    // Need more inventory
                    $additionalQuantity = $quantityDifference;
                    $inventoryReduced = $this->inventoryModel->reduceStock($variantId, $additionalQuantity);
                    
                    if (!$inventoryReduced) {
                        $this->productBookingModel->rollback();
                        $this->jsonError("Cannot increase quantity. Only have inventory for {$currentQuantity} units.", 400);
                        return;
                    }
                    
                    error_log("Additional inventory of {$additionalQuantity} units reduced for variant {$variantId}");
                }
                // If quantity is decreasing, we would need to add the excess back to inventory
                // This could be implemented in a future update if needed
            }
            
            // Prepare update data
            $updateData = [];
            
            if (!empty($data['status'])) {
                $updateData['PB_STATUS'] = $data['status'];
            }
            
            if (isset($data['quantity']) && $data['quantity'] > 0) {
                $updateData['PB_QUANTITY'] = $data['quantity'];
            }
            
            if (!empty($data['preferredDate'])) {
                $updateData['PB_PREFERRED_DATE'] = $data['preferredDate'];
            }
            
            if (!empty($data['preferredTime'])) {
                $updateData['PB_PREFERRED_TIME'] = $data['preferredTime'];
            }
            
            if (!empty($data['priceType'])) {
                // Validate price type
                if (!in_array($data['priceType'], ['free_install', 'with_install'])) {
                    $this->productBookingModel->rollback();
                    $this->jsonError('Invalid price type. Must be "free_install" or "with_install"', 400);
                    return;
                }
                $updateData['PB_PRICE_TYPE'] = $data['priceType'];
            }
            
            if (!empty($data['description'])) {
                $updateData['PB_DESCRIPTION'] = $data['description'];
            }
            
            // Update the booking
            $updateResult = $this->productBookingModel->updateBooking($bookingId, $updateData);
            error_log("Booking update result: " . ($updateResult ? "Success" : "Failed"));
            
            // Update technician assignments if provided
            if (isset($data['technicians'])) {
                // Remove current assignments
                $this->productBookingModel->removeAllTechnicians($bookingId);
                
                // Add new assignments only if technicians array is not empty
                if (!empty($data['technicians'])) {
                    foreach ($data['technicians'] as $tech) {
                        $this->productBookingModel->assignTechnician($bookingId, $tech['id'], $tech['notes'] ?? '');
                    }
                }
            }
            
            // Commit transaction
            $commitResult = $this->productBookingModel->commit();
            error_log("Transaction commit result: " . ($commitResult ? "Success" : "Failed"));
            
            $this->jsonSuccess(null, 'Booking updated successfully');
            
        } catch (\Exception $e) {
            $this->productBookingModel->rollback();
            error_log("Error updating booking: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->jsonError('An error occurred while updating the booking: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete a product booking (admin)
     */
    public function deleteProductBooking($id)
    {
        // Check if user is admin
        if (!$this->isAdmin()) {
            $this->jsonError('Unauthorized access', 401);
            return;
        }
        
        // Check if booking exists
        $booking = $this->productBookingModel->getBookingById($id);
        if (!$booking) {
            $this->jsonError('Booking not found', 404);
            return;
        }
        
        // Start transaction
        $this->productBookingModel->beginTransaction();
        
        try {
            // If booking was confirmed, we should add the inventory back
            $status = isset($booking['PB_STATUS']) ? $booking['PB_STATUS'] : (isset($booking['pb_status']) ? $booking['pb_status'] : '');
            
            if ($status === 'confirmed') {
                // Get variant ID and quantity
                $variantId = isset($booking['PB_VARIANT_ID']) ? intval($booking['PB_VARIANT_ID']) : intval($booking['pb_variant_id'] ?? 0);
                $quantity = isset($booking['PB_QUANTITY']) ? intval($booking['PB_QUANTITY']) : intval($booking['pb_quantity'] ?? 0);
                
                if ($variantId && $quantity > 0) {
                    // Ensure inventoryModel is initialized
                    if (!isset($this->inventoryModel)) {
                        $this->inventoryModel = new \App\Models\InventoryModel();
                    }
                    
                    error_log("Adding back inventory for deleted booking - VariantID: {$variantId}, Quantity: {$quantity}");
                    
                    // Add stock back to inventory as Regular type in the first warehouse
                    // You might want to enhance this logic to determine which warehouse to add to
                    $warehouseModel = new \App\Models\WarehouseModel();
                    $warehouses = $warehouseModel->getAllWarehouses();
                    
                    if (!empty($warehouses)) {
                        $warehouseId = isset($warehouses[0]['WHOUSE_ID']) ? $warehouses[0]['WHOUSE_ID'] : (isset($warehouses[0]['whouse_id']) ? $warehouses[0]['whouse_id'] : null);
                        
                        if ($warehouseId) {
                            $stockAdded = $this->inventoryModel->addStock($variantId, $warehouseId, $quantity, 'Regular');
                            error_log("Inventory restoration result: " . ($stockAdded ? "Success" : "Failed"));
                            
                            if (!$stockAdded) {
                                error_log("Failed to restore inventory for variant {$variantId} with quantity {$quantity}");
                                // Continue with deletion even if inventory restoration fails
                            }
                        }
                    }
                }
            }
            
            // Delete the booking
            $result = $this->productBookingModel->deleteBooking($id);
            
            if (!$result) {
                $this->productBookingModel->rollback();
                $this->jsonError('Failed to delete booking', 500);
                return;
            }
            
            // Commit transaction
            $this->productBookingModel->commit();
            $this->jsonSuccess(null, 'Booking deleted successfully');
            
        } catch (\Exception $e) {
            $this->productBookingModel->rollback();
            error_log("Error deleting booking: " . $e->getMessage());
            $this->jsonError('An error occurred while deleting the booking', 500);
        }
    }

    /**
     * Check if current user is an admin
     */
    private function isAdmin()
    {
        // Get the current user role from the session
        $userRole = $_SESSION['user_role'] ?? null;
        
        // Check if user is an admin
        return $userRole === 'admin';
    }

    /**
     * Get user account information
     */
    private function getUserInfo($userId) 
    {
        $sql = "SELECT 
                UA_ID,
                UA_PROFILE_URL,
                UA_FIRST_NAME,
                UA_LAST_NAME,
                UA_EMAIL,
                UA_PHONE_NUMBER
            FROM USER_ACCOUNT 
            WHERE UA_ID = :userId";
            
        // Use pdo instead of db
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database error in getUserInfo: " . $e->getMessage());
            return null;
        }
    }
} 