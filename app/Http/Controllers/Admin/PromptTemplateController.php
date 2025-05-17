<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\GeminiVisionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <-- Penting untuk otorisasi
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;


class PromptTemplateController extends Controller
{
    protected GeminiVisionService $geminiService; // Inject service

    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    // // ... (metode index, create, store, edit, update, destroy Anda) ...

    // /**
    //  * Test a given prompt configuration with an image.
    //  */
    // public function testPrompt(Request $request)
    // {
    //     // Otorisasi dasar
    //     if (!Auth::check() || Auth::user()->role !== 'Admin') {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Maks 5MB
    //         'target_prompt' => 'required|string',
    //         'condition_prompt' => 'required|string',
    //         'label_guidance' => 'required|string',
    //         'output_instructions' => 'required|string',
    //         'generation_config_json' => ['nullable', 'json'], // Terima sebagai string JSON
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $imageFile = $request->file('image');
    //     $targetPrompt = $request->input('target_prompt');
    //     $conditionPrompt = $request->input('condition_prompt');
    //     $labelGuidance = $request->input('label_guidance');
    //     $outputInstructions = $request->input('output_instructions');
    //     $generationConfigJson = $request->input('generation_config_json');

    //     $generationConfig = null;
    //     if ($generationConfigJson) {
    //         $decodedConfig = json_decode($generationConfigJson, true);
    //         if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
    //             $generationConfig = $decodedConfig;
    //         } else {
    //             Log::warning('TestPrompt: Invalid JSON for generation_config', ['json_string' => $generationConfigJson]);
    //             // Anda bisa mengembalikan error atau menggunakan default jika JSON tidak valid
    //             // return response()->json(['error' => 'Invalid JSON format for generation config.'], 400);
    //         }
    //     }

    //     // Membangun prompt lengkap dari input
    //     // Anda bisa menyempurnakan ini sesuai cara Anda membangun prompt di GeminiVisionService
    //     $fullPrompt = "Target: " . $targetPrompt . "\n" .
    //         "Condition: " . $conditionPrompt . "\n" .
    //         "Label Guidance: " . $labelGuidance . "\n" .
    //         "Output Instructions: " . $outputInstructions;

    //     Log::info('TestPrompt: Testing with custom prompt and config.', [
    //         'prompt_length' => strlen($fullPrompt),
    //         'has_gen_config' => !is_null($generationConfig)
    //     ]);

    //     try {
    //         // Panggil metode di GeminiVisionService yang menerima prompt string, gambar, dan config
    //         // Anda mungkin perlu membuat metode baru di GeminiVisionService jika belum ada
    //         // yang bisa menerima semua parameter ini secara fleksibel.
    //         // Atau, untuk sementara, kita bisa panggil API langsung dari sini jika lebih mudah.

    //         // Asumsi GeminiVisionService memiliki metode seperti ini:
    //         // $results = $this->geminiService->analyzeWithCustomPrompt(
    //         //     $imageFile,
    //         //     $fullPrompt,
    //         //     $generationConfig // Bisa null
    //         // );
    //         $results = $this->geminiService->analyzeWithCustomPromptAndConfig(
    //             $imageFile,
    //             $fullPrompt,
    //             $generationConfig
    //         );
    //         // Untuk sekarang, kita tiru cara GeminiVisionService memanggil API,
    //         // tapi menggunakan prompt dan config dari request.
    //         // Ini adalah PENGULANGAN LOGIKA, idealnya ini ada di service.
    //         $apiKey = config('services.google.api_key');
    //         $apiEndpoint = config('services.google.gemini_api_endpoint_flash'); // atau endpoint pro

    //         if (!$apiKey || !$apiEndpoint) {
    //             Log::error('TestPrompt: Google API Key or Endpoint not configured.');
    //             return response()->json(['error' => 'Google API Key or Endpoint not configured.'], 500);
    //         }

    //         $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
    //         $payload = [
    //             'contents' => [
    //                 [
    //                     'parts' => [
    //                         ['text' => $fullPrompt],
    //                         ['inline_data' => ['mime_type' => $imageFile->getMimeType(), 'data' => $imageData]]
    //                     ]
    //                 ]
    //             ],
    //         ];
    //         if ($generationConfig) {
    //             $payload['generationConfig'] = $generationConfig;
    //         }

    //         $ch = curl_init($apiEndpoint . '?key=' . $apiKey);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($ch, CURLOPT_POST, true);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    //         // Tambahkan opsi timeout jika perlu
    //         // curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30 detik

    //         $response = curl_exec($ch);
    //         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //         $curlError = curl_error($ch);
    //         curl_close($ch);

    //         if ($curlError) {
    //             Log::error('TestPrompt: cURL Error calling Gemini API', ['error' => $curlError]);
    //             return response()->json(['error' => 'Failed to call Gemini API: ' . $curlError], 500);
    //         }
    //         if ($httpCode !== 200) {
    //             Log::error('TestPrompt: Gemini API Error', ['http_code' => $httpCode, 'response' => $response]);
    //             return response()->json(['error' => 'Gemini API returned error: ' . $httpCode, 'details' => json_decode($response, true)], $httpCode);
    //         }

    //         $geminiResponse = json_decode($response, true);
    //         // Parsing sederhana, Anda mungkin perlu parsing yang lebih canggih seperti di GeminiVisionService
    //         $candidates = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
    //         $parsedResults = [];
    //         if ($candidates) {
    //             $jsonAttempt = json_decode(trim($candidates), true);
    //             if (json_last_error() === JSON_ERROR_NONE && is_array($jsonAttempt)) {
    //                 $parsedResults = $jsonAttempt;
    //             } else {
    //                 $parsedResults = [['raw_text' => $candidates]]; // Jika bukan JSON, kirim sebagai teks mentah
    //             }
    //         }

    //         Log::info('TestPrompt: Gemini API call successful.', ['response_preview' => Str::limit(json_encode($parsedResults), 200)]);

    //         return response()->json([
    //             'success' => true,
    //             'gemini_raw_response_text' => $results['raw_text'],
    //             'parsed_results' => $results['parsed_data'],
    //             'full_prompt_sent' => $fullPrompt,
    //             'generation_config_used' => $generationConfig
    //         ]);
    //         // return response()->json([
    //         //     'success' => true,
    //         //     'gemini_raw_response_text' => $candidates, // Teks mentah dari Gemini
    //         //     'parsed_results' => $parsedResults,     // Hasil parsing jika JSON
    //         //     'full_prompt_sent' => $fullPrompt,        // Untuk debug
    //         //     'generation_config_used' => $generationConfig // Untuk debug
    //         // ]);
    //     } catch (\Exception $e) {
    //         Log::error('TestPrompt: Exception during Gemini API call: ' . $e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(), 500)]);
    //         return response()->json(['error' => 'An internal server error occurred: ' . $e->getMessage()], 500);
    //     }
    // }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Otorisasi dasar (hanya Admin)
        // if (auth()->user()->role !== 'Admin') {
        //     abort(403, 'ANDA TIDAK DIIZINKAN MENGAKSES HALAMAN INI.');
        // }

        $searchTerm = $request->query('search');

        $promptTemplates = PromptTemplate::query()
            ->when($searchTerm, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prompts/Index', [
            'promptTemplates' => $promptTemplates,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return Inertia::render('Admin/Prompts/Create', [
            // Kirim data tambahan jika perlu untuk form create
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // return redirect()->route('admin.prompt-templates.index')->with('success', 'Template prompt berhasil dibuat.');
        return response()->json(['message' => 'Store method not yet implemented.'], 501);
    }

    /**
     * Display the specified resource.
     */
    public function show(PromptTemplate $promptTemplate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PromptTemplate $promptTemplate)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        return Inertia::render('Admin/Prompts/Edit', [
            'promptTemplate' => $promptTemplate,
            // Kirim data tambahan jika perlu
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PromptTemplate $promptTemplate)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        // Implementasi validasi dan update
        // ...
        // Cache::forget('active_prompt_template'); // Jika is_active diubah
        // return redirect()->route('admin.prompt-templates.index')->with('success', 'Template prompt berhasil diperbarui.');
        return response()->json(['message' => 'Update method not yet implemented.'], 501); // Placeholder
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PromptTemplate $promptTemplate)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        // if ($promptTemplate->is_active) {
        //     return redirect()->route('admin.prompt-templates.index')->with('error', 'Tidak dapat menghapus template yang sedang aktif.');
        // }
        // $promptTemplate->delete();
        // return redirect()->route('admin.prompt-templates.index')->with('success', 'Template prompt berhasil dihapus.');
        return response()->json(['message' => 'Destroy method not yet implemented.'], 501); // Placeholder
    }

    /**
     * Activate the specified prompt template.
     */
    public function activate(PromptTemplate $promptTemplate)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        // DB::transaction(function () use ($promptTemplate) {
        //     PromptTemplate::where('is_active', true)->update(['is_active' => false]);
        //     $promptTemplate->update(['is_active' => true]);
        //     Cache::forget('active_prompt_template');
        // });
        // return redirect()->route('admin.prompt-templates.index')->with('success', "Template '{$promptTemplate->name}' berhasil diaktifkan.");
        return response()->json(['message' => 'Activate method not yet implemented.'], 501); // Placeholder
    }


    /**
     * Test a given prompt configuration with an image.
     */
    public function testPrompt(Request $request)
    {
        // Otorisasi sudah ditangani oleh middleware rute 'role:Admin'
        // if (!Auth::check() || Auth::user()->role !== 'Admin') { // Cek tambahan jika middleware tidak cukup spesifik
        //     return response()->json(['error' => 'Unauthorized or insufficient permissions.'], 403);
        // }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'target_prompt' => 'required|string',
            'condition_prompt' => 'required|string',
            'label_guidance' => 'required|string',
            'output_instructions' => 'required|string',
            'generation_config_json' => ['nullable', 'json'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imageFile = $request->file('image');
        $targetPrompt = $request->input('target_prompt');
        $conditionPrompt = $request->input('condition_prompt');
        $labelGuidance = $request->input('label_guidance');
        $outputInstructions = $request->input('output_instructions');
        $generationConfigJson = $request->input('generation_config_json');
        $generationConfig = null;

        if ($generationConfigJson) {
            $decodedConfig = json_decode($generationConfigJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
                $generationConfig = $decodedConfig;
            } else {
                Log::warning('TestPrompt: Invalid JSON for generation_config', ['json_string' => $generationConfigJson]);
            }
        }

        $fullPrompt = "Target: " . $targetPrompt . "\n" .
            "Condition: " . $conditionPrompt . "\n" .
            "Label Guidance: " . $labelGuidance . "\n" .
            "Output Instructions: " . $outputInstructions;

        Log::info('TestPrompt: Testing with custom prompt and config.', [ /* ... */]);

        try {
            $results = $this->geminiService->analyzeWithCustomPromptAndConfig(
                $imageFile,
                $fullPrompt,
                $generationConfig
            );

            // Cek apakah ada error dari service (misalnya, cURL error atau API error)
            if (isset($results['curl_error']) && $results['curl_error']) {
                return response()->json(['error' => 'Failed to call Gemini API: ' . $results['curl_error']], 500);
            }
            if ($results['http_code'] !== 200) {
                return response()->json(['error' => 'Gemini API returned error: ' . $results['http_code'], 'details' => $results['parsed_data'] ?? $results['raw_text']], $results['http_code']);
            }


            return response()->json([
                'success' => true,
                'gemini_raw_response_text' => $results['raw_text'],
                'parsed_results' => $results['parsed_data'],
                'full_prompt_sent' => $fullPrompt,
                'generation_config_used' => $generationConfig
            ]);
        } catch (\Exception $e) {
            Log::error('TestPrompt: Exception: ' . $e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(), 500)]);
            return response()->json(['error' => 'An internal server error occurred: ' . $e->getMessage()], 500);
        }
    }
}
