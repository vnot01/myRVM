<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver; // Atau Imagick jika Anda prefer dan sudah terinstall
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Http\UploadedFile;
use Exception; // Import class Exception
use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Cache;
use App\Models\ConfiguredPrompt;
use Illuminate\Support\Str;


class GeminiVisionService
{
    ////=============================////
    ////=== Custome Vision API v4 ===////
    ////=============================////

    // Metode getActivePromptTemplate dan analyzeImageFromFile bisa dihapus jika tidak dipakai lagi
    // protected function getActivePromptTemplate(): PromptTemplate { ... }
    // public function analyzeImageFromFile(UploadedFile $imageFile): ?array { ... }
    protected string $apiKey;
    protected string $apiEndpointFlash;
    // protected string $apiEndpointPro; // Jika Anda ingin mudah switch

    public function __construct()
    {
        Log::info('GeminiVisionService: __construct called.');
        $this->apiKey = config('services.google.api_key');
        // $this->apiEndpointFlash = config('services.google.api_endpoint_flash');
        // $this->apiEndpointFlash = config('services.google.api_endpoint_pro');
        $this->apiEndpointFlash = config('services.google.api_endpoint_2_5_flash_preview');
        // $this->apiEndpointFlash = config('services.google.api_endpoint_2_5_pro_preview');
        // $this->apiEndpointFlash = config('services.google.api_endpoint_pro_latest');
        // $this->apiEndpointFlash = config('services.google.api_endpoint_flash_latest');

        if (!$this->apiKey || !$this->apiEndpointFlash) { // Cek endpoint flash sebagai default
            Log::critical('GeminiService FATAL: API Key or Default Endpoint is not configured!');
            throw new Exception('Gemini API Key or Default Endpoint is not configured.');
        }
    }

    /**
     * Metode utama yang dipanggil oleh RvmController untuk analisis gambar
     * menggunakan prompt yang sedang aktif.
     */
    public function analyzeImageUsingActivePrompt(UploadedFile $imageFile): array
    {
        Log::info('GeminiVisionService: analyzeImageUsingActivePrompt called.');
        $activeConfiguredPrompt = $this->getActiveConfiguredPrompt();

        if (!$activeConfiguredPrompt) {
            Log::error('GeminiService: No active configured prompt found for RVM operation.');
            return ['error' => 'No active prompt system', 'parsed_data' => [['label' => 'REJECTED_SYSTEM_ERROR', 'reason' => 'No active prompt']]];
        }

        $fullPrompt = $activeConfiguredPrompt->full_prompt_text_generated;
        $generationConfig = $activeConfiguredPrompt->generation_config_final; // Ini sudah array dari cast model

        Log::info('GeminiService: Using active configured prompt for RVM.', [
            'prompt_name' => $activeConfiguredPrompt->configured_prompt_name,
            'prompt_length' => strlen($fullPrompt)
        ]);

        list($imageBase64, $imageMimeType) = $this->processAndEncodeImage($imageFile);

        // Panggil metode inti yang melakukan call API
        $apiResult = $this->executeGeminiVisionRequest($imageBase64, $imageMimeType, $fullPrompt, $generationConfig, $this->apiEndpointFlash);

        // Kembalikan hasil parsing atau struktur error
        if ($apiResult['http_code'] !== 200 || (isset($apiResult['parsed_data']['error']))) {
             Log::error('GeminiService: Error during active prompt analysis.', ['api_result' => $apiResult]);
             return $apiResult['parsed_data'] ?? [['label' => 'REJECTED_API_ERROR', 'raw_text' => $apiResult['raw_text']]];
        }
        return $apiResult['parsed_data'] ?? [['label' => 'REJECTED_PARSING_ERROR', 'raw_text' => $apiResult['raw_text']]];
    }

    /**
     * Metode yang dipanggil oleh PromptTemplateController@testPrompt.
     * Menerima gambar, prompt string lengkap, dan config generasi.
     */
    public function analyzeWithCustomPromptAndConfig(UploadedFile $imageFile, string $customPrompt, ?array $generationConfig = null): array
    {
        Log::info('GeminiService: analyzeWithCustomPromptAndConfig called (Test Prompt).');
        list($imageBase64, $imageMimeType) = $this->processAndEncodeImage($imageFile);
        return $this->executeGeminiVisionRequest($imageBase64, $imageMimeType, $customPrompt, $generationConfig, $this->apiEndpointFlash);
    }

    /**
     * Metode inti untuk melakukan HTTP request ke Gemini Vision API.
     */
    protected function executeGeminiVisionRequest(string $imageBase64, string $imageMimeType, string $prompt, ?array $generationConfig, string $endpoint): array
    {
        Log::info('GeminiVisionService: executeGeminiVisionRequest called.');
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $imageMimeType, 'data' => $imageBase64]]
                ]
            ]],
        ];
        if ($generationConfig) { $payload['generationConfig'] = $generationConfig; }
        $fullApiUrl = $endpoint . '?key=' . $this->apiKey;
        Log::info('GeminiService: Preparing cURL request.', ['url' => $fullApiUrl, 'prompt_length' => strlen($prompt), 'has_gen_config' => !is_null($generationConfig)]);
        // Log::debug('GeminiService: Payload to be sent:', $payload); // Bisa sangat besar karena imageBase64

        $startTime = microtime(true);
        $ch = curl_init(); // Inisialisasi di sini

        if ($ch === false) {
            Log::error('GeminiService: Failed to initialize cURL.');
            return ['raw_text' => null, 'parsed_data' => ['error' => 'cURL initialization failed'], 'http_code' => null, 'curl_error' => 'Failed to initialize cURL'];
        }

        curl_setopt($ch, CURLOPT_URL, $fullApiUrl); // Set URL di sini
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        // Untuk debugging SSL lebih lanjut (biasanya tidak diperlukan jika server Anda up-to-date)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/your/cacert.pem'); // Jika perlu sertifikat kustom

        Log::info('GeminiService: Executing cURL request...');
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNumber = curl_errno($ch); // Dapatkan nomor error cURL
        $curlErrorMessage = curl_error($ch); // Dapatkan pesan error cURL
        curl_close($ch);
        $endTime = microtime(true);

        Log::info('GeminiService: cURL execution finished.', [
            'http_code' => $httpCode,
            'duration_seconds' => round($endTime - $startTime, 3),
            'curl_error_number' => $curlErrorNumber,
            'curl_error_message' => $curlErrorMessage,
            'response_body_length' => strlen($responseBody ?: '')
        ]);
        // Log::debug('GeminiService: Raw Response Body from Gemini:', ['body' => $responseBody]); // Hati-hati jika respons besar

        if ($curlErrorNumber !== 0) { // Cek nomor error, bukan hanya pesan
            Log::error('GeminiService: cURL Error during API call', ['error_number' => $curlErrorNumber, 'error_message' => $curlErrorMessage]);
            return ['raw_text' => null, 'parsed_data' => ['error' => 'cURL Error: ' . $curlErrorMessage], 'http_code' => $httpCode, 'curl_error' => $curlErrorMessage];
        }

        if ($httpCode !== 200) {
            Log::error('GeminiService: Gemini API HTTP Error', ['http_code' => $httpCode, 'response_body' => Str::limit($responseBody, 500)]);
            $decodedError = json_decode($responseBody, true);
            return ['raw_text' => $responseBody, 'parsed_data' => ['error' => 'Gemini API Error ' . $httpCode, 'details' => $decodedError ?? $responseBody], 'http_code' => $httpCode, 'curl_error' => null];
        }

        // Jika $responseBody kosong atau bukan JSON yang valid, json_decode akan return null
        $geminiResponse = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('GeminiService: Failed to decode JSON response from Gemini.', ['json_error' => json_last_error_msg(), 'response_body' => Str::limit($responseBody, 500)]);
            return ['raw_text' => $responseBody, 'parsed_data' => ['error' => 'Invalid JSON response from Gemini', 'details' => json_last_error_msg()], 'http_code' => $httpCode, 'curl_error' => null];
        }

        $rawText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
        Log::info('GeminiService: Raw text extracted from Gemini response.', ['raw_text_present' => !is_null($rawText)]);

        return [
            'raw_text' => $rawText,
            'parsed_data' => $this->parseAndCleanGeminiText($rawText),
            'http_code' => $httpCode,
            'curl_error' => null
        ];
    }

    /**
     * Mengekstrak dan mem-parsing blok JSON dari teks respons Gemini.
     */
    protected function parseAndCleanGeminiText(?string $rawText): array
    {
        info('GeminiVisionService: parseAndCleanGeminiText called.');
        if ($rawText === null || trim($rawText) === '') {
            Log::warning('GeminiService: Raw text from Gemini is empty or null for parsing.');
            return ['error' => 'Empty response text from Gemini'];
        }

        $jsonString = null;
        // Regex untuk mengekstrak konten di dalam ```json ... ```
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $rawText, $matches)) {
            $jsonString = trim($matches[1]);
            info('GeminiService: Extracted JSON string using ```json block.', ['length' => strlen($jsonString)]);
        } elseif (strpos(trim($rawText), '{') === 0 && strrpos(trim($rawText), '}') === strlen(trim($rawText)) - 1) {
            // Jika teks dimulai dengan { dan diakhiri dengan }, coba parse langsung
            // Ini mungkin perlu disempurnakan jika ada teks tambahan setelah blok JSON utama
            $jsonString = trim($rawText);
             // Cari posisi '{' pertama dan '}' terakhir yang paling luar
            $firstBrace = strpos($jsonString, '{');
            $lastBrace = strrpos($jsonString, '}');
            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $jsonString = substr($jsonString, $firstBrace, $lastBrace - $firstBrace + 1);
            } else {
                $jsonString = null; // Tidak bisa menemukan blok JSON yang valid
            }
            Log::info('GeminiService: Attempting direct JSON parse as text starts with { and ends with }.', ['length' => strlen($jsonString ?? '')]);
        } else {
            Log::warning('GeminiService: No clear JSON block (```json) or direct JSON object found.', ['raw_text_preview' => Str::limit($rawText, 100)]);
            // Jika tidak ada blok JSON, kembalikan teks mentah sebagai bagian dari error atau hasil
            return ['error' => 'No parsable JSON block found', 'raw_text_if_no_json' => $rawText];
        }

        if ($jsonString) {
            $parsedData = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
                Log::info('GeminiService: Successfully parsed extracted JSON.');
                return $parsedData; // Berhasil diparsing
            } else {
                Log::error('GeminiService: Failed to parse extracted JSON string.', [
                    'json_string_attempted' => Str::limit($jsonString, 200),
                    'json_error' => json_last_error_msg(),
                ]);
                return ['error' => 'Failed to parse extracted JSON: ' . json_last_error_msg(), 'extracted_json_string' => $jsonString];
            }
        }
        // Fallback jika $jsonString null setelah semua usaha
        return ['error' => 'Could not extract a valid JSON string from Gemini response', 'raw_text_if_no_json' => $rawText];
    }


    protected function processAndEncodeImage(UploadedFile $imageFile): array
    {
       info('GeminiVisionService: processAndEncodeImage - Start processing.');
        try {
            info('GeminiVisionService: processAndEncodeImage - Preparing to initialized $manager = new ImageManager(new Driver())');
            $manager = new ImageManager(new Driver());
            // Atau ImagickDriver jika itu yang Anda gunakan
            info('GeminiVisionService: processAndEncodeImage - ImageManager initialized.');
            $img = $manager->read($imageFile->getRealPath());
            info('GeminiVisionService: processAndEncodeImage - Image read by manager.');
            // ScaleDown (Resize)
            $img->scaleDown(width: 800, height: 800); // Atau resolusi lain yang sesuai
            info('GeminiVisionService: processAndEncodeImage - Image scaled down.');
            // Convert to JPEG (atau format lain yang didukung Gemini) dan encode ke base64
            $resizedImageData = $img->toJpeg(85)->toString(); // Kualitas 85%
            $imageMimeType = 'image/jpeg';
            info('GeminiVisionService: processAndEncodeImage - Image converted to JPEG string.');
            $imageBase64 = base64_encode($resizedImageData);
            if (empty($imageBase64)) {
                Log::error('GeminiVisionService: processAndEncodeImage - Failed to base64 encode image data. Image string might be empty after toJpeg().');
                throw new Exception('Failed to encode image.');
            }
            info('GeminiVisionService: processAndEncodeImage - Image base64 encoded successfully.', ['mime_type' => $imageMimeType, 'base64_length' => strlen($imageBase64)]);
            return [$imageBase64, $imageMimeType];
        } catch (\Intervention\Image\Exception\NotReadableException $e) {
            Log::error('GeminiVisionService: processAndEncodeImage - Intervention Image NotReadableException: ' . $e->getMessage(), ['file_path' => $imageFile->getRealPath()]);
            throw new Exception('Failed to read image file (Intervention): ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('GeminiVisionService: processAndEncodeImage - General Exception: ' . $e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(), 500)]);
            throw new Exception('Failed to process image: ' . $e->getMessage());
        }
    }

    public function getActiveConfiguredPrompt(): ?ConfiguredPrompt
    {
        $cacheKey = config('services.google.active_prompt_cache_key', 'gemini_active_configured_prompt');
        // Cache selama misal 60 menit, atau sampai di-forget saat ada aktivasi baru
        // return Cache::remember($cacheKey, now()->addHour(), function () {
        //     return ConfiguredPrompt::where('is_active', true)->first();
        // });
        return Cache::remember($cacheKey, now()->addHour(), fn (): ConfiguredPrompt|null => ConfiguredPrompt::where('is_active', true)->first());
    }

    ////=================================////
    ////=== End Custome Vision API v4 ===////
    ////=================================////

    // ////=============================////
    // ////=== Custome Vision API v3 ===////
    // ////=============================////
    // protected string $apiKey;
    // protected string $apiEndpoint;
    // public function __construct()
    // {
    //     $this->apiKey = config('services.google.api_key');
    //     $this->apiEndpoint = config('services.google.api_endpoint_flash');
    //     if (!$this->apiKey || !$this->apiEndpoint) {
    //         throw new Exception('Gemini API Key or Endpoint is not configured in services config.');
    //     }
    // }
    // public function analyzeWithCustomPromptAndConfig($imageFile, string $customPrompt, ?array $generationConfig = null): array
    // {
    //     $apiKey = config('services.google.api_key');
    //     $apiEndpoint = config('services.google.api_endpoint_flash'); // atau pro
    //     // $apiEndpoint = config('services.google.api_endpoint_pro'); // atau pro

    //     if (!$apiKey || !$apiEndpoint) {
    //         Log::error('GeminiService: Google API Key or Endpoint not configured for custom prompt.');
    //         throw new \Exception('Google API Key or Endpoint not configured.');
    //     }

    //     $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
    //     $payload = [
    //         'contents' => [
    //             [
    //                 'parts' => [
    //                     ['text' => $customPrompt],
    //                     ['inline_data' => ['mime_type' => $imageFile->getMimeType(), 'data' => $imageData]]
    //                 ]
    //             ]
    //         ],
    //     ];
    //     if ($generationConfig) {
    //         $payload['generationConfig'] = $generationConfig;
    //     }

    //     // ... (Logika cURL untuk memanggil API Gemini - sama seperti di atas) ...
    //     // ... (Parsing respons - sama seperti di atas) ...
    //     // return $parsedResults; // atau array yang lebih lengkap dengan raw_text juga

    //     // --- PASTIKAN INISIALISASI cURL ADA DI SINI ---
    //     $ch = curl_init($apiEndpoint . '?key=' . $apiKey);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    //     // curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Opsional

    //     $response = curl_exec($ch);
    //     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //     $curlError = curl_error($ch);
    //     curl_close($ch); // Ini sudah ada sebelumnya

    //     $geminiResponse = json_decode($response, true);
    //     $rawText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
    //     $parsedData = [];
    //     if ($rawText) {
    //         $jsonAttempt = json_decode(trim($rawText), true);
    //         if (json_last_error() === JSON_ERROR_NONE && is_array($jsonAttempt)) {
    //             $parsedData = $jsonAttempt;
    //         } else {
    //             $parsedData = ['error' => 'Failed to parse Gemini response as JSON', 'raw_text' => $rawText];
    //         }
    //     }
    //     return [
    //         'raw_text' => $rawText,
    //         'parsed_data' => $parsedData,
    //         'http_code' => $httpCode,
    //         'curl_error' => $curlError // Kembalikan juga curl error untuk debug
    //     ];
    // }

    // ////=======================////
    // //// === Vision API v3 === ////
    // ////=======================////

    // // app/Services/GeminiVisionService.php
    // // ...
    // public function getActiveConfiguredPrompt(): ?ConfiguredPrompt // Ubah return type
    // {
    //     $cacheKey = config('services.google.active_prompt_cache_key', 'gemini_active_configured_prompt');
    //     // Cache selama misal 60 menit, atau sampai di-forget saat ada aktivasi baru
    //     return Cache::remember($cacheKey, now()->addHour(), function () {
    //         return ConfiguredPrompt::where('is_active', true)->first();
    //     });
    // }

    // // Metode utama yang dipanggil RvmController akan menggunakan ini
    // public function analyzeImageUsingActivePrompt($imageFile)
    // {
    //     $activeConfiguredPrompt = $this->getActiveConfiguredPrompt();

    //     if (!$activeConfiguredPrompt) {
    //         Log::error('GeminiService: No active configured prompt found.');
    //         // Kembalikan struktur error standar atau throw exception
    //         return [['label' => 'REJECTED_SYSTEM_ERROR', 'reason' => 'No active prompt']];
    //     }

    //     $fullPrompt = $activeConfiguredPrompt->full_prompt_text_generated;
    //     $generationConfig = $activeConfiguredPrompt->generation_config_final; // Ini sudah array

    //     Log::info('GeminiService: Using active configured prompt.', [
    //         'prompt_name' => $activeConfiguredPrompt->configured_prompt_name,
    //         'prompt_length' => strlen($fullPrompt)
    //     ]);

    //     // Panggil metode yang melakukan call API dengan prompt, gambar, dan config
    //     $analysisResult = $this->analyzeWithCustomPromptAndConfig($imageFile, $fullPrompt, $generationConfig);

    //     // Kembalikan hasil parsing atau response mentah sesuai kebutuhan RvmController
    //     return $analysisResult['parsed_data'] ?? [['label' => 'REJECTED_PARSING_ERROR', 'raw_text' => $analysisResult['raw_text']]];
    // }

    // /**
    //  * Mendapatkan prompt template yang sedang aktif dari database (dengan caching).
    //  */
    // protected function getActivePromptTemplate(): PromptTemplate
    // {
    //     return Cache::remember('active_prompt_template', now()->addMinutes(60), function () {
    //         Log::debug('Fetching active prompt template from DB (cache miss)'); // Log jika cache miss
    //         $template = PromptTemplate::where('is_active', true)->first();
    //         if (!$template) {
    //             Log::error('No active prompt template found in database!');
    //             throw new Exception('No active prompt template configured.');
    //         }
    //         return $template;
    //     });
    // }

    // public function analyzeImageFromFile(UploadedFile $imageFile): ?array
    // {
    //     try {
    //         list($imageBase64, $imageMimeType) = $this->processAndEncodeImage($imageFile);

    //         $activeTemplate = $this->getActivePromptTemplate();
    //         $prompt = $activeTemplate->buildFullPrompt();

    //         // Ambil generation config dan PASTIKAN itu array
    //         $generationConfig = $activeTemplate->generation_config; // $casts seharusnya sudah membuatnya jadi array

    //         // TAMBAHKAN PENGECEKAN DAN DECODE MANUAL JIKA PERLU:
    //         if (is_string($generationConfig)) {
    //             Log::warning('Generation config was retrieved as a string, attempting to decode manually.', [
    //                 'template_id' => $activeTemplate->id,
    //                 'string_config' => $generationConfig
    //             ]);
    //             // Coba decode string JSON menjadi array
    //             $decodedConfig = json_decode($generationConfig, true);
    //             // Periksa apakah decoding berhasil dan hasilnya array
    //             if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
    //                 $generationConfig = $decodedConfig; // Gunakan hasil decode
    //             } else {
    //                 Log::error('Failed to manually decode generation_config string from database.', [
    //                     'template_id' => $activeTemplate->id,
    //                     'json_error' => json_last_error_msg()
    //                 ]);
    //                 $generationConfig = null; // Set ke null jika decode gagal
    //             }
    //         } elseif (!is_array($generationConfig) && $generationConfig !== null) {
    //             // Jika bukan string, bukan array, dan bukan null (tipe data aneh)
    //             Log::warning('Generation config has unexpected type, setting to null.', [
    //                 'template_id' => $activeTemplate->id,
    //                 'type' => gettype($generationConfig)
    //             ]);
    //             $generationConfig = null;
    //         }
    //         // Pada titik ini, $generationConfig seharusnya sudah berupa array atau null

    //         Log::debug('Using prompt template:', ['name' => $activeTemplate->name]);
    //         Log::debug('Built prompt:', ['prompt' => $prompt]);
    //         // Log $generationConfig SETELAH potensi decode manual
    //         Log::debug('Generation config (final):', ['config' => $generationConfig]);

    //         return $this->callGeminiApi($imageBase64, $imageMimeType, $prompt, $generationConfig); // $generationConfig sekarang pasti array atau null

    //     } catch (Exception $e) {
    //         Log::error('GeminiVisionService - analyzeImageFromFile Error: ' . $e->getMessage(), [
    //             'trace' => substr($e->getTraceAsString(), 0, 500)
    //         ]);
    //         throw $e;
    //     }
    // }

    // protected function processAndEncodeImage(UploadedFile $imageFile): array
    // {
    //     // ... (Kode ini tetap sama seperti sebelumnya) ...
    //     try {
    //         $manager = new ImageManager(new GdDriver());
    //         $img = $manager->read($imageFile->getRealPath());
    //         $img->scaleDown(width: 800, height: 800);
    //         $resizedImageData = $img->toJpeg(85)->toString();
    //         $imageMimeType = 'image/jpeg';
    //         $imageBase64 = base64_encode($resizedImageData);
    //         if (empty($imageBase64)) {
    //             throw new Exception('Failed to encode image.');
    //         }
    //         return [$imageBase64, $imageMimeType];
    //     } catch (Exception $e) {
    //         Log::error('Image processing failed: ' . $e->getMessage());
    //         throw new Exception('Failed to process image: ' . $e->getMessage());
    //     }
    // }

    // /**
    //  * Memanggil Google Gemini Vision API menggunakan prompt dan config dari template.
    //  */
    // protected function callGeminiApi(string $imageBase64, string $imageMimeType, string $prompt, ?array $generationConfig): ?array
    // {
    //     $payload = ['contents' => [['parts' => [['text' => $prompt], ['inline_data' => ['mime_type' => $imageMimeType, 'data' => $imageBase64]]]]]];
    //     if (!empty($generationConfig)) {
    //         $payload['generationConfig'] = $generationConfig;
    //     }

    //     Log::debug('Sending payload to Gemini:', ['endpoint' => $this->apiEndpoint, 'payload_keys' => array_keys($payload)]);
    //     Log::info('Gemini Service - Preparing to call Google API...', ['endpoint' => $this->apiEndpoint]);
    //     $startTime = microtime(true); // Catat waktu mulai
    //     $response = Http::timeout(60)->withHeaders(['Content-Type' => 'application/json'])
    //         ->post($this->apiEndpoint . '?key=' . $this->apiKey, $payload);
    //     $endTime = microtime(true); // Catat waktu selesai
    //     Log::info('Gemini Service - Google API call completed.', [
    //         'status_code' => $response->status(),
    //         'duration_seconds' => round($endTime - $startTime, 3)
    //     ]);
    //     if (!$response->successful()) { /* ... (Error handling sama) ... */
    //         $errorBody = $response->body();
    //         Log::error('Gemini API Error:', ['status' => $response->status(), 'body' => $errorBody]);
    //         $apiErrorDetails = $response->json();
    //         $errorMessage = 'Failed to call Gemini API. Status: ' . $response->status();
    //         if (isset($apiErrorDetails['error']['message'])) {
    //             $errorMessage .= ' Details: ' . $apiErrorDetails['error']['message'];
    //         } else {
    //             $errorMessage .= ' Body: ' . $errorBody;
    //         }
    //         throw new Exception($errorMessage);
    //     }

    //     $responseText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
    //     // ... (Logika penanganan $responseText null/kosong sama) ...
    //     if ($responseText === null) {
    //         $finishReason = $response->json()['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    //         if ($finishReason === 'SAFETY') {
    //             throw new Exception('Gemini API request blocked due to safety settings.');
    //         }
    //         Log::info('Gemini API response had no text part.', ['response' => $response->json()]);
    //     }
    //     if (trim($responseText ?? '') === '') {
    //         Log::info('Gemini API returned empty string. Assuming empty list.');
    //         $responseText = '[]';
    //     }

    //     return $this->parseGeminiResponse($responseText);
    // }

    // /**
    //  * Mem-parsing respons JSON mentah dari Gemini.
    //  */
    // protected function parseGeminiResponse(?string $responseText): array
    // {
    //     // ... (Kode ini tetap sama seperti sebelumnya) ...
    //     if ($responseText === null || trim($responseText) === '') {
    //         return [];
    //     }
    //     $jsonString = $responseText;
    //     if (strpos($jsonString, '```json') !== false) {
    //         if (preg_match('/```json\s*([\s\S]*?)\s*```/', $jsonString, $matches)) {
    //             $jsonString = $matches[1];
    //         } else {
    //             $jsonString = str_replace(['```json', '```'], '', $jsonString);
    //         }
    //     }
    //     $jsonString = str_replace('```', '', trim($jsonString));
    //     try {
    //         $parsedResponse = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    //         if (!is_array($parsedResponse)) {
    //             if ($jsonString === '[]' && is_array($parsedResponse)) {
    //                 return $parsedResponse;
    //             }
    //             Log::warning('Parsed Gemini response is not a list.', ['original' => $responseText, 'parsed_str' => $jsonString, 'result' => $parsedResponse]);
    //             return [];
    //         }
    //         return $parsedResponse;
    //     } catch (\JsonException $e) {
    //         Log::error('Failed to parse JSON from Gemini:', ['error' => $e->getMessage(), 'text' => $responseText]);
    //         throw new Exception('Failed to parse JSON response from Gemini: ' . $e->getMessage());
    //     }
    // }

    ////===========================////
    //// === Vision API v3 END === ////
    ////===========================////


    ////=======================////
    //// === Vision API v2 === ////
    ////=======================////
    // protected string $apiKey;
    // protected string $apiEndpoint;

    // public function __construct()
    // {
    //     // Ambil dari config yang sudah kita setup sebelumnya
    //     $this->apiKey = config('services.google.api_key');
    //     $this->apiEndpoint = config('services.google.api_endpoint_flash'); // Menggunakan endpoint flash

    //     if (!$this->apiKey || !$this->apiEndpoint) {
    //         throw new Exception('Gemini API Key or Endpoint is not configured in services config.');
    //     }
    // }

    // /**
    //  * Menganalisis gambar dari file yang diunggah.
    //  *
    //  * @param UploadedFile $imageFile
    //  * @return array|null Hasil analisis atau null jika gagal.
    //  * @throws Exception
    //  */
    // public function analyzeImageFromFile(UploadedFile $imageFile): ?array
    // {
    //     try {
    //         list($imageBase64, $imageMimeType) = $this->processAndEncodeImage($imageFile);
    //         return $this->callGeminiApi($imageBase64, $imageMimeType);
    //     } catch (Exception $e) {
    //         Log::error('GeminiVisionService - analyzeImageFromFile Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString() // Untuk debugging lebih detail
    //         ]);
    //         throw $e; // Re-throw exception agar bisa ditangani oleh pemanggil
    //     }
    // }

    // /**
    //  * Memproses gambar (resize) dan meng-encode ke base64.
    //  *
    //  * @param UploadedFile $imageFile
    //  * @return array [$imageBase64, $imageMimeType]
    //  * @throws Exception
    //  */
    // protected function processAndEncodeImage(UploadedFile $imageFile): array
    // {
    //     try {
    //         // Gunakan driver GD atau Imagick. GD biasanya lebih umum tersedia.
    //         $manager = new ImageManager(new GdDriver());
    //         $img = $manager->read($imageFile->getRealPath());

    //         // Resize gambar (misalnya, lebar atau tinggi maksimum, atau ukuran tetap)
    //         // $img->resize(800, 600); // Contoh resize ke ukuran tetap
    //         $img->scaleDown(width: 800, height: 800); // Resize dengan menjaga rasio, maksimal 800x800

    //         // Konversi ke format yang didukung Gemini (JPEG atau PNG direkomendasikan)
    //         // dan dapatkan data binary nya
    //         $resizedImageData = $img->toJpeg(85)->toString(); // Kualitas 85%
    //         $imageMimeType = 'image/jpeg';

    //         $imageBase64 = base64_encode($resizedImageData);

    //         if (empty($imageBase64)) {
    //             throw new Exception('Failed to encode image to base64 after processing.');
    //         }

    //         return [$imageBase64, $imageMimeType];
    //     } catch (Exception $e) {
    //         Log::error('Image processing failed in GeminiVisionService: ' . $e->getMessage());
    //         throw new Exception('Failed to process the uploaded image: ' . $e->getMessage());
    //     }
    // }

    // /**
    //  * Membangun prompt untuk Gemini API.
    //  *
    //  * @return string
    //  */
    // protected function buildPrompt(): string
    // {
    //     $targetPrompt = "plastic bottles (like mineral water, soda, tea, coffee bottles) or aluminum cans. Distinguish between different bottle types if possible.";
    //     $conditionPrompt = "Determine if each item appears **EMPTY** (no visible liquid, debris, or significant residue) or **FILLED/CONTAMINATED** (contains visible liquid like water, visible trash like cigarette butts or sticks, or is significantly crushed/unsuitable). Be precise about emptiness.";
    //     $labelGuidance = "Provide a concise label describing the item type and its condition. Examples: 'EMPTY mineral water bottle', 'EMPTY aluminum can', 'FILLED soda bottle - liquid visible', 'CONTAMINATED PET bottle - trash visible', 'CRUSHED aluminum can'.";

    //     // Gabungkan dengan instruksi output JSON yang ketat
    //     return "Analyze the image to detect {$targetPrompt}. For each detected item, {$conditionPrompt}. " .
    //         "Output ONLY a valid JSON list (no extra text or markdown formatting like \`\`\`json ... \`\`\`) containing distinct items found, with a maximum of 5 items. " .
    //         "Each entry in the list must be an object containing: " .
    //         "1. 'box_2d': The 2D bounding box ([ymin, xmin, ymax, xmax] scaled 0-1000). " .
    //         "2. 'label': {$labelGuidance} " .
    //         "If no relevant items are found, output an empty JSON list [].";
    // }

    // /** 
    //  *    Lebih fokus pada pembedaan utama: kosong vs tidak kosong
    //  */
    // // protected function buildPrompt(): string
    // // {
    // //     $targetPrompt = "plastic bottles (mineral water, soda, tea, etc.) or aluminum cans.";
    // //     $conditionFocus = "Critically assess if the item is **EMPTY** (no visible contents or significant residue) or **NOT EMPTY** (has visible liquid, trash, or is crushed).";
    // //     $labelFormat = "Use labels like: 'EMPTY PET bottle', 'EMPTY aluminum can', 'NOT EMPTY PET bottle', 'NOT EMPTY aluminum can'. Specify bottle type (e.g., 'mineral water', 'soda') only if clearly identifiable.";
    // //     return "Detect {$targetPrompt} in the image. For each item, {$conditionFocus} " .
    // //         "Output ONLY a valid JSON list (no extra text or markdown formatting) of distinct items, max 5. " .
    // //         "Each object must have 'box_2d' ([ymin, xmin, ymax, xmax] scaled 0-1000) and 'label'. {$labelFormat} " .
    // //         "Output an empty JSON list [] if no items are found.";
    // // }

    // /**
    //  * Memanggil Google Gemini Vision API.
    //  *
    //  * @param string $imageBase64
    //  * @param string $imageMimeType
    //  * @return array|null
    //  * @throws Exception
    //  */
    // protected function callGeminiApi(string $imageBase64, string $imageMimeType): ?array
    // {
    //     $prompt = $this->buildPrompt();

    //     $payload = [
    //         'contents' => [
    //             [
    //                 'parts' => [
    //                     ['text' => $prompt],
    //                     ['inline_data' => [
    //                         'mime_type' => $imageMimeType,
    //                         'data' => $imageBase64
    //                     ]]
    //                 ]
    //             ]
    //         ],
    //         'generationConfig' => [
    //             'candidateCount' => 1,
    //             'maxOutputTokens' => 1024, // Kurangi sedikit jika tidak perlu output panjang
    //             'temperature' => 0.2, // Lebih rendah -> lebih deterministik, kurang acak/kreatif
    //             'topP' => 0.95, 
    //             'topK' => 40,
    //         ]
    //     ];

    //     $response = Http::timeout(60) // Timeout 60 detik
    //         ->withHeaders(['Content-Type' => 'application/json'])
    //         ->post($this->apiEndpoint . '?key=' . $this->apiKey, $payload);

    //     if (!$response->successful()) {
    //         $errorBody = $response->body();
    //         Log::error('Gemini API Error:', [
    //             'status' => $response->status(),
    //             'body' => $errorBody,
    //             'endpoint' => $this->apiEndpoint
    //         ]);
    //         // Coba parse error dari Gemini jika ada
    //         $apiErrorDetails = $response->json();
    //         $errorMessage = 'Failed to call Gemini API. Status: ' . $response->status();
    //         if (isset($apiErrorDetails['error']['message'])) {
    //             $errorMessage .= ' Details: ' . $apiErrorDetails['error']['message'];
    //         } else {
    //             $errorMessage .= ' Body: ' . $errorBody;
    //         }
    //         throw new Exception($errorMessage);
    //     }

    //     // Ekstrak teks dari respons
    //     // Struktur respons Gemini Vision bisa sedikit berbeda, pastikan path ini sesuai
    //     // 'candidates'[0]['content']['parts'][0]['text']
    //     $responseText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

    //     if ($responseText === null) { // Bisa jadi respons sukses tapi tidak ada 'text' (jarang terjadi jika prompt valid)
    //         // Periksa apakah ada 'finishReason' seperti 'SAFETY'
    //         $finishReason = $response->json()['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    //         if ($finishReason === 'SAFETY') {
    //             Log::warning('Gemini API call blocked due to safety reasons.', ['response' => $response->json()]);
    //             throw new Exception('Gemini API request was blocked due to safety settings.');
    //         } elseif (isset($response->json()['candidates'][0]['content']['parts']) && empty($response->json()['candidates'][0]['content']['parts'])) {
    //             // Ini berarti API mengembalikan 'parts' array kosong, yang bisa diartikan tidak ada deteksi yang valid
    //             // atau model tidak menghasilkan output teks. Untuk kasus kita (meminta JSON list),
    //             // ini bisa berarti "empty JSON list []" adalah output yang valid jika prompt dihandle dengan baik oleh model.
    //             // Kita akan tangani ini di tahap parsing.
    //             Log::info('Gemini API response had no text part, but was successful. Potentially an empty detection.', ['response' => $response->json()]);
    //             // Jika model mengembalikan array kosong "[]" sebagai output teks valid, parsing akan menghasilkan array kosong.
    //         } else {
    //             Log::error('Gemini response did not contain expected text part.', ['response' => $response->json()]);
    //             throw new Exception('Gemini response structure error: text part missing.');
    //         }
    //     }

    //     // Jika responseText adalah string kosong atau hanya whitespace, dan kita mengharapkan JSON list,
    //     // ini bisa dianggap sebagai "tidak ada deteksi yang valid" atau output yang tidak diharapkan.
    //     // Namun, model mungkin valid mengembalikan "[]" sebagai teks.
    //     if (trim($responseText ?? '') === '') {
    //         // Periksa jika prompt kita secara eksplisit meminta "[]" untuk 'no items found'
    //         // Jika ya, maka ini adalah hasil yang valid.
    //         Log::info('Gemini API returned an empty string as text part. Assuming empty detection list as per prompt design.', ['response' => $response->json()]);
    //         $responseText = '[]'; // Asumsikan ini sebagai daftar kosong jika prompt kita mendukungnya.
    //     }


    //     return $this->parseGeminiResponse($responseText);
    // }

    // /**
    //  * Mem-parsing respons JSON mentah dari Gemini.
    //  * (Menghilangkan markdown, dll.)
    //  *
    //  * @param string|null $responseText
    //  * @return array
    //  * @throws Exception
    //  */
    // protected function parseGeminiResponse(?string $responseText): array
    // {
    //     if ($responseText === null || trim($responseText) === '') {
    //         // Jika setelah semua pengecekan di callGeminiApi, responseText masih null atau kosong,
    //         // dan kita berharap JSON, ini adalah masalah. Namun, jika prompt kita bisa menghasilkan "[]",
    //         // maka ini bisa jadi valid.
    //         // Kita sudah handle ini dengan men-set $responseText = '[]' di atas.
    //         Log::info('Parsing an empty or null responseText as an empty list.');
    //         return []; // Kembalikan array kosong jika tidak ada teks atau teksnya kosong.
    //     }

    //     $jsonString = $responseText;

    //     // Menghilangkan ```json ... ``` jika ada (model kadang masih menyertakannya)
    //     if (strpos($jsonString, '```json') !== false) {
    //         if (preg_match('/```json\s*([\s\S]*?)\s*```/', $jsonString, $matches)) {
    //             $jsonString = $matches[1];
    //         } else {
    //             // Fallback jika regex tidak cocok tapi ```json ada
    //             $jsonString = str_replace(['```json', '```'], '', $jsonString);
    //         }
    //     }
    //     // Juga hilangkan ``` saja jika hanya itu yang ada
    //     $jsonString = str_replace('```', '', trim($jsonString));


    //     try {
    //         $parsedResponse = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    //         // Pastikan hasilnya adalah array (list)
    //         if (!is_array($parsedResponse)) {
    //             // Jika hasil parse bukan array (misalnya string atau objek tunggal padahal kita minta list)
    //             // dan $jsonString sebenarnya adalah '[]', maka $parsedResponse akan jadi array kosong, itu valid.
    //             // Tapi jika $jsonString bukan '[]' dan hasilnya bukan array, itu masalah.
    //             if ($jsonString === '[]' && is_array($parsedResponse)) { // ini kondisi valid
    //                 return $parsedResponse;
    //             }
    //             Log::warning('Parsed Gemini response is not a list (array).', ['original_text' => $responseText, 'parsed_string' => $jsonString, 'parsed_result' => $parsedResponse]);
    //             // Jika kita selalu mengharapkan list, ini bisa dianggap error atau kita kembalikan list kosong.
    //             // throw new Exception('Gemini response, after parsing, was not in the expected list format.');
    //             return []; // Atau kembalikan array kosong jika ini bisa terjadi
    //         }
    //         return $parsedResponse; // Seharusnya ini adalah list (array PHP) dari objek deteksi
    //     } catch (\JsonException $e) {
    //         Log::error('Failed to parse JSON response from Gemini:', [
    //             'error' => $e->getMessage(),
    //             'original_text' => $responseText,
    //             'processed_string_for_json_decode' => $jsonString
    //         ]);
    //         throw new Exception('Failed to parse JSON response from Gemini: ' . $e->getMessage());
    //     }
    // }
    ////===========================////
    //// === Vision API v2 END === ////
    ////===========================////

    ////=======================////
    //// === Vision API v1 === ////
    ////=======================////
    // protected string $apiKey;
    // protected string $apiEndpoint;

    // public function __construct()
    // {
    //     // Ambil API Key dan Endpoint dari config/services.php
    //     // Pastikan Anda sudah mengkonfigurasi ini dengan benar dan merujuk ke GEMINI_API_ENDPOINT_FLASH
    //     $this->apiKey = config('services.google.api_key');
    //     $this->apiEndpoint = config('services.google.api_endpoint_flash'); // Menggunakan endpoint flash

    //     if (!$this->apiKey || !$this->apiEndpoint) {
    //         Log::error('Gemini API Key or Endpoint is not configured in services.php.');
    //         // Anda bisa throw exception di sini jika ingin menghentikan proses jika tidak terkonfigurasi
    //         throw new \Exception('Gemini API Key or Endpoint is not configured.');
    //     }
    // }

    // /**
    //  * Analyze an image using Google Gemini Vision API.
    //  *
    //  * @param \Illuminate\Http\UploadedFile|\Symfony\Component\HttpFoundation\File\File|string $imageInput Path to image, UploadedFile object, or base64 string
    //  * @param string $targetPrompt Specific items to look for in the image.
    //  * @param string $labelPrompt How to describe the items found.
    //  * @return array Parsed response from Gemini or throws an Exception on failure.
    //  * @throws \Exception
    //  */
    // public function analyzeImage($imageInput, string $targetPrompt, string $labelPrompt): array
    // {
    //     if (!$this->apiKey || !$this->apiEndpoint) {
    //         throw new \Exception('Gemini API Key or Endpoint is not configured.');
    //     }

    //     $imageBase64 = null;
    //     $imageMimeType = null;

    //     try {
    //         // === Langkah Resizing (Intervention Image v3) ===
    //         $manager = new ImageManager(new Driver()); // Gunakan Driver GD atau Imagick
    //         $img = null;

    //         if ($imageInput instanceof \Illuminate\Http\UploadedFile || $imageInput instanceof \Symfony\Component\HttpFoundation\File\File) {
    //             $img = $manager->read($imageInput->getRealPath());
    //             $imageMimeType = $imageInput->getMimeType(); // Dapatkan mime type asli
    //         } elseif (is_string($imageInput) && file_exists($imageInput)) { // Jika path file
    //             $img = $manager->read($imageInput);
    //             $imageMimeType = mime_content_type($imageInput);
    //         } elseif (is_string($imageInput) && str_starts_with($imageInput, 'data:image')) { // Jika data URI base64
    //             // Handle base64 input jika diperlukan, atau minta UploadedFile/path
    //             // Untuk sekarang, kita fokus pada file input
    //             throw new \Exception('Base64 image input processing not fully implemented in this example. Please provide a file path or UploadedFile object.');
    //         } else {
    //             throw new \Exception('Invalid image input type.');
    //         }

    //         // Resize gambar
    //         $img->resize(400, 400); // Atau ->cover(400, 400) atau ->fit(400, 400)

    //         // Tentukan mime type untuk encoding, default ke jpeg jika tidak terdeteksi
    //         $outputMimeType = 'image/jpeg';
    //         if (in_array(strtolower($imageMimeType ?? ''), ['image/png', 'image/webp'])) {
    //             // $outputMimeType = strtolower($imageMimeType); // Jika ingin mempertahankan PNG/WebP
    //             // Jika tetap JPEG untuk konsistensi ukuran request
    //             $encodedImage = $img->toJpeg(90); // Kualitas 90%
    //         } else { // Default ke JPEG
    //             $encodedImage = $img->toJpeg(90);
    //         }
    //         $imageBase64 = base64_encode($encodedImage);
    //         // === Akhir Resizing ===

    //     } catch (\Exception $e) {
    //         Log::error('Intervention Image processing failed:', ['error' => $e->getMessage()]);
    //         throw new \Exception('Failed to process the uploaded image. ' . $e->getMessage());
    //     }

    //     if (empty($imageBase64)) { // Mime type tidak perlu dicek di sini karena sudah di-handle $outputMimeType
    //         throw new \Exception('Image data could not be prepared for the API.');
    //     }

    //     // === Siapkan Prompt & Panggil Gemini API ===
    //     // Contoh prompt dari diskusi sebelumnya
    //     // $targetPrompt = "bottles, identifying if they are mineral water bottles or other types (like soda, tea, coffee bottles), and whether they appear empty or filled (note potential contents like water, cigarette butts, sticks of wood, etc if visible)";
    //     // $labelPrompt = "a label describing the bottle type and fill status (e.g., 'empty mineral bottle', 'filled soda bottle - water', 'filled tea bottle - trash')";
    //     $fullPrompt = "Detect {$targetPrompt}, with no more than 20 items. Output ONLY a valid JSON list (no extra text or markdown formatting) where each entry contains the 2D bounding box in \"box_2d\" (as [ymin, xmin, ymax, xmax] scaled 0-1000) and {$labelPrompt} in \"label\".";

    //     $payload = [
    //         'contents' => [
    //             [
    //                 'parts' => [
    //                     ['text' => $fullPrompt],
    //                     ['inline_data' => [
    //                         'mime_type' => $outputMimeType, // Gunakan outputMimeType
    //                         'data' => $imageBase64
    //                     ]]
    //                 ]
    //             ]
    //         ],
    //         // Anda bisa menambahkan generationConfig di sini jika perlu
    //         // 'generationConfig' => [
    //         //     'candidateCount' => 1,
    //         //     'maxOutputTokens' => 2048,
    //         //     'temperature' => 0.5, // Sesuaikan untuk kreativitas vs faktual
    //         //     'topP' => 1,
    //         //     'topK' => 32,
    //         // ]
    //     ];

    //     $response = Http::timeout(60) // Timeout 60 detik
    //         ->withHeaders(['Content-Type' => 'application/json'])
    //         ->post($this->apiEndpoint . '?key=' . $this->apiKey, $payload);

    //     if (!$response->successful()) {
    //         Log::error('Gemini API Error:', ['status' => $response->status(), 'body' => $response->body()]);
    //         $apiErrorDetails = $response->json() ?? $response->body();
    //         throw new \Exception('Failed to call Gemini API. Status: ' . $response->status() . ' Details: ' . json_encode($apiErrorDetails));
    //     }

    //     // Parsing JSON response dari Gemini
    //     // Teks respons mungkin berada di dalam 'candidates'[0]['content']['parts'][0]['text']
    //     $responseText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
    //     if (!$responseText) {
    //         Log::warning('Gemini response did not contain expected text part.', ['response_body' => $response->body()]);
    //         throw new \Exception('Gemini response did not contain expected text part or the structure is different.');
    //     }

    //     // Menghilangkan ```json dan ``` dari respons
    //     $jsonString = $responseText;
    //     if (strpos($responseText, '```json') !== false) {
    //         if (preg_match('/```json\s*([\s\S]*?)\s*```/', $responseText, $matches)) {
    //             $jsonString = $matches[1];
    //         } else {
    //             // Fallback jika regex gagal tapi ```json ada
    //             $jsonString = str_replace(['```json', '```'], '', $responseText);
    //         }
    //     }

    //     try {
    //         $parsedResponse = json_decode(trim($jsonString), true, 512, JSON_THROW_ON_ERROR);
    //     } catch (\JsonException $e) {
    //         Log::error('Failed to parse JSON response from Gemini:', ['raw_text' => $responseText, 'error' => $e->getMessage()]);
    //         throw new \Exception('Failed to parse JSON response from Gemini. Raw text: ' . $responseText);
    //     }

    //     // Di sini, $parsedResponse adalah array PHP dari JSON yang dikembalikan Gemini.
    //     // Anda mungkin perlu melakukan format ulang atau validasi tambahan di sini atau di controller yang memanggil service ini.
    //     // Contoh: Format Bounding Box jika diperlukan
    //     // $formattedBoxes = [];
    //     // if (is_array($parsedResponse)) {
    //     //     foreach ($parsedResponse as $box) {
    //     //         if (isset($box['box_2d']) && is_array($box['box_2d']) && count($box['box_2d']) === 4 && isset($box['label'])) {
    //     //             [$ymin, $xmin, $ymax, $xmax] = $box['box_2d'];
    //     //             $formattedBoxes[] = [
    //     //                 'x' => $xmin / 1000, 
    //     //                 'y' => $ymin / 1000, 
    //     //                 'width' => ($xmax - $xmin) / 1000, 
    //     //                 'height' => ($ymax - $ymin) / 1000, 
    //     //                 'label' => $box['label']
    //     //             ];
    //     //         } else {
    //     //             Log::warning('Skipping invalid box data from Gemini in service:', ['box_data' => $box]);
    //     //         }
    //     //     }
    //     // } else {
    //     //      Log::warning('Parsed Gemini response is not an array as expected.', ['parsed_response' => $parsedResponse]);
    //     // }
    //     // return ['boundingBoxes' => $formattedBoxes];

    //     return $parsedResponse; // Kembalikan array yang sudah diparsing
    // }
    ////===========================////
    //// === Vision API v1 END === ////
    ////===========================////
}
