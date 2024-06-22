<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * store
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        $tenant = Tenant::first();
        $shopUrl = $tenant->domain;
        $token = $tenant->token;
        $isCreate = true;

        try {
            $getProduct = $this->getProduct($request->product['kode']);
            if ($getProduct) {
                $product = Product::find($getProduct->id)->update([
                    'data' => json_encode($request->all()),
                ]);
                $product = Product::find($getProduct->id);
            } else {
                $product = Product::create([
                    'sku' => $request->product['kode'],
                    'data' => json_encode($request->all()),
                ]);
            }

            if ($product->shop_product_id == null) {
                $getProductShopify = $this->getProductShopify($shopUrl, $token, $product->sku);
                if (isset($getProductShopify['data']['products']['edges']) &&
                !empty($getProductShopify['data']['products']['edges'])) {
                    $productShopifyId = $getProductShopify['data']['products']['edges'][0]['node']['legacyResourceId'];
                    $productUpdate = Product::find($product->id)->update([
                        'shop_product_id' => $productShopifyId,
                    ]);
                    $response = $this->updateProductShopify($request->product, $shopUrl, $token, $product->shop_product_id);
                    $isCreate = false;
                } else {
                    $response = $this->createProductShopify($request->product, $shopUrl, $token);
                    $isCreate = true;
                    $productUpdate = Product::find($product->id)->update([
                        'shop_product_id' => $response->json()['product']['id'],
                    ]);
                }
            } else {
                $response = $this->updateProductShopify($request->product, $shopUrl, $token, $product->shop_product_id);
                $isCreate = false;
            }

            if ($response->status() == 200 || $response->status() == 201) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product ' . ($isCreate ? 'create' : 'update' ). ' successfully',
                    'product' => $response->json()
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ' . ($isCreate ? 'create' : 'update') . ' failed'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product from table
     *
     * @param string $sku
     * @return Product
     */
    protected function getProduct($sku)
    {
        $product = Product::where('sku', $sku)->first();
        return $product;
    }

    /**
     * Get product from shopify by sku
     *
     * @param string $shopUrl
     * @param string $token
     * @param string $sku
     * @return mixed
     */
    protected function getProductShopify($shopUrl, $token, $sku)
    {
        $query = 'query {
            products(first: 1, query: "sku:'.$sku.'") {
              edges {
                node {
                    legacyResourceId
                }
              }
            }
        }';

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->post("https://{$shopUrl}/admin/api/2024-04/graphql.json", [
                'query' => $query
            ]);

        return $response->json();
    }

    /**
     * Created product to shopify
     *
     * @param array $data
     * @param string $shopUrl
     * @param string $token
     * @return mixed
     */
    protected function createProductShopify($data, $shopUrl, $token)
    {
        $images = [];
        $payloads = [];
        $variants = [
            'price' => (float)$data['harga'],
            'sku' => $data['kode'],
            'title' => $data['nama'],
            'weight' => (float)$data['berat'],
        ];

        foreach ($data['gambar'] as $image) {
            $images[] = [
                'src' => $image['image']
            ];
        }

        $payloads['product'] = [
            'title' => $data['nama'],
            'body_html' => $data['deskripsi'],
            'status' => $data['status'] == 'Enable' ? 'active' : 'draft',
            'images' => $images,
            'variants' => [$variants],
            'weight_unit' => 'kg'
        ];

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->post("https://{$shopUrl}/admin/api/2024-04/products.json", $payloads);

        return $response;
    }

    /**
     * Created product to shopify
     *
     * @param array $data
     * @param string $shopUrl
     * @param string $token
     * @return mixed
     */
    protected function updateProductShopify($data, $shopUrl, $token, $productId)
    {
        $images = [];
        $payloads = [];
        $variants = [
            'price' => (float)$data['harga'],
            'sku' => $data['kode'],
            'title' => $data['nama'],
            'weight' => (float)$data['berat'],
        ];

        foreach ($data['gambar'] as $image) {
            $images[] = [
                'src' => $image['image']
            ];
        }

        $payloads['product'] = [
            'title' => $data['nama'],
            'body_html' => $data['deskripsi'],
            'status' => $data['status'] == 'Enable' ? 'active' : 'draft',
            'images' => $images,
            'variants' => [$variants],
            'weight_unit' => 'kg'
        ];
        Log::debug(json_encode($payloads));

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->put("https://{$shopUrl}/admin/api/2024-04/products/".$productId.".json", $payloads);

        return $response;
    }
}
