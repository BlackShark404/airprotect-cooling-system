<?php

namespace App\Controllers;

class ProductController extends BaseController
{
    protected $productModel;
    protected $productVariantModel;
    protected $productFeatureModel;
    protected $productSpecModel;
    protected $inventoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = $this->loadModel('ProductModel');
        $this->productVariantModel = $this->loadModel('ProductVariantModel');
        $this->productFeatureModel = $this->loadModel('ProductFeatureModel');
        $this->productSpecModel = $this->loadModel('ProductSpecModel');
        $this->inventoryModel = $this->loadModel('InventoryModel');
    }

    /**
     * Get all products
     */
    public function getAllProducts()
    {
        if (!$this->isAjax()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $products = $this->productModel->getProductsWithDetails();
            $this->jsonSuccess($products);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get product by ID
     */
    public function getProduct($id)
    {
        if (!$this->isAjax()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $product = $this->productModel->getProductWithDetails($id);
            
            if (!$product) {
                $this->jsonError('Product not found', 404);
            }
            
            // Get variants
            $variants = $this->productVariantModel->getVariantsByProductId($id);
            $product['variants'] = $variants;
            
            // Get total stock
            $product['total_stock'] = $this->inventoryModel->getTotalProductStock($id);
            
            $this->jsonSuccess($product);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Create a new product
     */
    public function createProduct()
    {
        if (!$this->isAjax() || !$this->isPost()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $data = $this->getJsonInput();
            
            // Validate required fields
            if (empty($data['PROD_NAME']) || empty($data['PROD_IMAGE']) || empty($data['PROD_AVAILABILITY_STATUS'])) {
                $this->jsonError('Missing required fields');
            }
            
            // Begin transaction
            $this->productModel->beginTransaction();
            
            // Create product
            $productId = $this->productModel->createProduct($data);
            
            if (!$productId) {
                $this->productModel->rollback();
                $this->jsonError('Failed to create product');
            }
            
            // Process variants if provided
            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $variant['PROD_ID'] = $productId;
                    $this->productVariantModel->createVariant($variant);
                }
            }
            
            // Process features if provided
            if (!empty($data['features']) && is_array($data['features'])) {
                $this->productFeatureModel->addFeaturesToProduct($productId, $data['features']);
            }
            
            // Process specifications if provided
            if (!empty($data['specs']) && is_array($data['specs'])) {
                $specs = [];
                foreach ($data['specs'] as $spec) {
                    if (!empty($spec['SPEC_NAME']) && !empty($spec['SPEC_VALUE'])) {
                        $specs[$spec['SPEC_NAME']] = $spec['SPEC_VALUE'];
                    }
                }
                if (!empty($specs)) {
                    $this->productSpecModel->addSpecsToProduct($productId, $specs);
                }
            }
            
            // Process initial inventory if provided
            if (!empty($data['inventory']) && is_array($data['inventory']) &&
                !empty($data['WHOUSE_ID']) && !empty($data['INVE_TYPE'])) {
                
                foreach ($data['inventory'] as $inventoryItem) {
                    if (!empty($inventoryItem['quantity']) && $inventoryItem['quantity'] > 0) {
                        $this->inventoryModel->updateProductQuantity(
                            $productId,
                            $data['WHOUSE_ID'],
                            $data['INVE_TYPE'],
                            $inventoryItem['quantity']
                        );
                    }
                }
            }
            
            $this->productModel->commit();
            $this->jsonSuccess(['id' => $productId], 'Product created successfully');
        } catch (\Exception $e) {
            $this->productModel->rollback();
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Update a product
     */
    public function updateProduct($id)
    {
        if (!$this->isAjax() || !$this->isPost()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $data = $this->getJsonInput();
            
            // Check if product exists
            $existingProduct = $this->productModel->getProductById($id);
            if (!$existingProduct) {
                $this->jsonError('Product not found', 404);
            }
            
            // Begin transaction
            $this->productModel->beginTransaction();
            
            // Update product
            $result = $this->productModel->updateProduct($id, $data);
            
            if (!$result) {
                $this->productModel->rollback();
                $this->jsonError('Failed to update product');
            }
            
            // Update variants if provided
            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    if (!empty($variant['VAR_ID'])) {
                        // Update existing variant
                        $this->productVariantModel->updateVariant($variant['VAR_ID'], $variant);
                    } else {
                        // Create new variant
                        $variant['PROD_ID'] = $id;
                        $this->productVariantModel->createVariant($variant);
                    }
                }
            }
            
            // Update features if provided
            if (isset($data['features'])) {
                // First, delete existing features
                $this->productFeatureModel->deleteFeaturesByProductId($id);
                
                // Then add new features
                if (!empty($data['features']) && is_array($data['features'])) {
                    $this->productFeatureModel->addFeaturesToProduct($id, $data['features']);
                }
            }
            
            // Update specifications if provided
            if (isset($data['specs'])) {
                // First, delete existing specs
                $this->productSpecModel->deleteSpecsByProductId($id);
                
                // Then add new specs
                if (!empty($data['specs']) && is_array($data['specs'])) {
                    $specs = [];
                    foreach ($data['specs'] as $spec) {
                        if (!empty($spec['SPEC_NAME']) && !empty($spec['SPEC_VALUE'])) {
                            $specs[$spec['SPEC_NAME']] = $spec['SPEC_VALUE'];
                        }
                    }
                    if (!empty($specs)) {
                        $this->productSpecModel->addSpecsToProduct($id, $specs);
                    }
                }
            }
            
            $this->productModel->commit();
            $this->jsonSuccess([], 'Product updated successfully');
        } catch (\Exception $e) {
            $this->productModel->rollback();
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct($id)
    {
        if (!$this->isAjax() || !$this->isPost()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            // Check if product exists
            $existingProduct = $this->productModel->getProductById($id);
            if (!$existingProduct) {
                $this->jsonError('Product not found', 404);
            }
            
            $result = $this->productModel->deleteProduct($id);
            
            if ($result) {
                $this->jsonSuccess([], 'Product deleted successfully');
            } else {
                $this->jsonError('Failed to delete product');
            }
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get product variants by product ID
     */
    public function getProductVariants($productId)
    {
        if (!$this->isAjax()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $variants = $this->productVariantModel->getVariantsByProductId($productId);
            $this->jsonSuccess($variants);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get product features by product ID
     */
    public function getProductFeatures($productId)
    {
        if (!$this->isAjax()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $features = $this->productFeatureModel->getFeaturesByProductId($productId);
            $this->jsonSuccess($features);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get product specifications by product ID
     */
    public function getProductSpecs($productId)
    {
        if (!$this->isAjax()) {
            $this->renderError('Invalid request', 400);
        }

        try {
            $specs = $this->productSpecModel->getSpecsByProductId($productId);
            $this->jsonSuccess($specs);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }
}
