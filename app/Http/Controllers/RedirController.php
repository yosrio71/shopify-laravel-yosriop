<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Tenant;

class RedirController extends Controller
{
    /**
     * Index
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $shop = $request->input('shop');
        $tenant = Tenant::where('domain', $shop)->first();

        try {
            $responseData = $this->getAccessToken($request->input('code'), $shop);
            if (!$tenant) {
                $tenant = Tenant::create([
                    'domain' => $shop,
                    'token' => $responseData['access_token'],
                ]);

            } else {
                Tenant::where('id', $tenant->id)->update([
                    'token' => $responseData['access_token'],
                ]);
            }
            $appsUrl = 'https://' . $shop . '/admin';
        } catch (\Exception $e) {
            Log::error('Redirect:: ' . $e->getMessage());
            return ;
        }

        return redirect()->to($appsUrl)->send();
    }

    /**
     * Get access token oauth shopify
     *
     * @param string $code
     * @param string $shop
     * @return mixed
     */
    protected function getAccessToken($code, $shop)
    {
        $data = [
            'client_id' => env('SHOPIFY_CLIENT_ID'),
            'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
            'code' => $code,
        ];

        $response = Http::withHeaders($data)->post("https://{$shop}/admin/oauth/access_token", $data);
        $responseData = $response->json();

        return $responseData;
    }
}
