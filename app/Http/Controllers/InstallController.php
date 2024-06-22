<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Tenant;

class InstallController extends Controller
{
    public function index(Request $request)
    {
        $apiKey = env('SHOPIFY_CLIENT_ID');
        $host = $request->getHttpHost();
        $shop = $request->input('shop');
        $isEmbedded = $request->input('embedded');
        $scopes = 'read_products,write_products';
        $nonce = bin2hex(random_bytes(12));
        $accessMode = 'offline';
        $redirectUri = urlencode("https://$host/redir");
        $tenant = Tenant::where('domain', $shop)->first();

        try {
            $isValidateHmac = $this->isValidateHmac($request);
    
            if (!$isValidateHmac) {
                Log::error('Install:: Invalid HMAC');
                return ;
            }

            if ($tenant) {
                # Return to home or etc if already installed apps
                Log::error(json_encode($tenant));
                return view('shopify_welcome');
            } else {
                # Redirect to process oauth token & store data for first time installed apps
                $path = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}&state={$nonce}&grant_options[]={$accessMode}";
                return redirect()->to($path)->send();
            }
        } catch (\Exception $e) {
            Log::error('Install:: ' . $e->getMessage());
            return ;
        }
    }

    protected function isValidateHmac($request)
    {
        $hmac = $request->input('hmac', '');
        $params = $request->all();
        $secret = env('SHOPIFY_CLIENT_SECRET');
        unset($params['hmac']);
        $calculatedHmac = hash_hmac('sha256', http_build_query($params), $secret);

        if (!hash_equals($calculatedHmac, $hmac)) {
            return false;
        }

        return true;
    }
}
