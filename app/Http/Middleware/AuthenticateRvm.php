<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ReverseVendingMachine;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRvm
{
    public function handle(Request $request, Closure $next): Response
    {
        // Mengambil API Key dari header 'X-RVM-ApiKey'
        // Atau bisa juga dari input body jika desainnya begitu: $request->input('rvm_api_key')
        // Penting untuk konsisten bagaimana RVM akan mengirimkan key ini.
        $rvmApiKey = $request->header('X-RVM-ApiKey');

        if (!$rvmApiKey) {
            // Jika dikirim via body, cek juga di sana sebagai fallback atau alternatif
            // $rvmApiKey = $request->input('rvm_api_key');
            // if (!$rvmApiKey) {
            // return response()->json(['status' => 'error', 'message' => 'RVM API Key missing.'], 401);
            // }
            return response()->json(['status' => 'error', 'message' => 'RVM API Key missing from header X-RVM-ApiKey.'], 401);
        }

        $rvm = ReverseVendingMachine::where('api_key', $rvmApiKey)->first();

        if (!$rvm) {
            return response()->json(['status' => 'error', 'message' => 'Invalid RVM API Key.'], 401);
        }

        if ($rvm->status !== 'active') {
            return response()->json(['status' => 'error', 'message' => 'RVM is not active. Current status: ' . $rvm->status], 403);
        }

        // Menyimpan instance RVM yang terotentikasi ke dalam objek request
        // agar bisa diakses dengan mudah di controller jika diperlukan.
        $request->attributes->set('authenticated_rvm', $rvm);

        return $next($request);
    }
}
