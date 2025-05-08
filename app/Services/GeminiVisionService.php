<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver; // Atau Imagick jika Anda prefer dan sudah terinstall
use Intervention\Image\ImageManager;
use Illuminate\Http\UploadedFile;
use Exception; // Import class Exception

class GeminiVisionService
{

    ////=======================////
    //// === Vision API v2 === ////
    ////=======================////
    protected string $apiKey;
    protected string $apiEndpoint;

    public function __construct()
    {
        // Ambil dari config yang sudah kita setup sebelumnya
        $this->apiKey = config('services.google.api_key');
        $this->apiEndpoint = config('services.google.api_endpoint_flash'); // Menggunakan endpoint flash

        if (!$this->apiKey || !$this->apiEndpoint) {
            throw new Exception('Gemini API Key or Endpoint is not configured in services config.');
        }
    }

    /**
     * Menganalisis gambar dari file yang diunggah.
     *
     * @param UploadedFile $imageFile
     * @return array|null Hasil analisis atau null jika gagal.
     * @throws Exception
     */
    public function analyzeImageFromFile(UploadedFile $imageFile): ?array
    {
        try {
            list($imageBase64, $imageMimeType) = $this->processAndEncodeImage($imageFile);
            return $this->callGeminiApi($imageBase64, $imageMimeType);
        } catch (Exception $e) {
            Log::error('GeminiVisionService - analyzeImageFromFile Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString() // Untuk debugging lebih detail
            ]);
            throw $e; // Re-throw exception agar bisa ditangani oleh pemanggil
        }
    }

    /**
     * Memproses gambar (resize) dan meng-encode ke base64.
     *
     * @param UploadedFile $imageFile
     * @return array [$imageBase64, $imageMimeType]
     * @throws Exception
     */
    protected function processAndEncodeImage(UploadedFile $imageFile): array
    {
        try {
            // Gunakan driver GD atau Imagick. GD biasanya lebih umum tersedia.
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($imageFile->getRealPath());

            // Resize gambar (misalnya, lebar atau tinggi maksimum, atau ukuran tetap)
            // $img->resize(800, 600); // Contoh resize ke ukuran tetap
            $img->scaleDown(width: 800, height: 800); // Resize dengan menjaga rasio, maksimal 800x800

            // Konversi ke format yang didukung Gemini (JPEG atau PNG direkomendasikan)
            // dan dapatkan data binary nya
            $resizedImageData = $img->toJpeg(85)->toString(); // Kualitas 85%
            $imageMimeType = 'image/jpeg';

            $imageBase64 = base64_encode($resizedImageData);

            if (empty($imageBase64)) {
                throw new Exception('Failed to encode image to base64 after processing.');
            }

            return [$imageBase64, $imageMimeType];
        } catch (Exception $e) {
            Log::error('Image processing failed in GeminiVisionService: ' . $e->getMessage());
            throw new Exception('Failed to process the uploaded image: ' . $e->getMessage());
        }
    }

    /**
     * Membangun prompt untuk Gemini API.
     *
     * @return string
     */
    protected function buildPrompt(): string
    {
        $targetPrompt = "plastic bottles (like mineral water, soda, tea, coffee bottles) or aluminum cans. Distinguish between different bottle types if possible.";
        $conditionPrompt = "Determine if each item appears **EMPTY** (no visible liquid, debris, or significant residue) or **FILLED/CONTAMINATED** (contains visible liquid like water, visible trash like cigarette butts or sticks, or is significantly crushed/unsuitable). Be precise about emptiness.";
        $labelGuidance = "Provide a concise label describing the item type and its condition. Examples: 'EMPTY mineral water bottle', 'EMPTY aluminum can', 'FILLED soda bottle - liquid visible', 'CONTAMINATED PET bottle - trash visible', 'CRUSHED aluminum can'.";

        // Gabungkan dengan instruksi output JSON yang ketat
        return "Analyze the image to detect {$targetPrompt}. For each detected item, {$conditionPrompt}. " .
            "Output ONLY a valid JSON list (no extra text or markdown formatting like \`\`\`json ... \`\`\`) containing distinct items found, with a maximum of 5 items. " .
            "Each entry in the list must be an object containing: " .
            "1. 'box_2d': The 2D bounding box ([ymin, xmin, ymax, xmax] scaled 0-1000). " .
            "2. 'label': {$labelGuidance} " .
            "If no relevant items are found, output an empty JSON list [].";
    }

    /**
     * Memanggil Google Gemini Vision API.
     *
     * @param string $imageBase64
     * @param string $imageMimeType
     * @return array|null
     * @throws Exception
     */
    protected function callGeminiApi(string $imageBase64, string $imageMimeType): ?array
    {
        $prompt = $this->buildPrompt();

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => [
                            'mime_type' => $imageMimeType,
                            'data' => $imageBase64
                        ]]
                    ]
                ]
            ],
            // Opsi tambahan untuk mengontrol output (opsional, tergantung kebutuhan)
            // 'generationConfig' => [
            //     'candidateCount' => 1,
            //     'maxOutputTokens' => 2048,
            //     'temperature' => 0.4, // Lebih rendah untuk output lebih deterministik
            //     'topP' => 1,
            //     'topK' => 32,
            // ]
        ];

        $response = Http::timeout(60) // Timeout 60 detik
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->apiEndpoint . '?key=' . $this->apiKey, $payload);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Gemini API Error:', [
                'status' => $response->status(),
                'body' => $errorBody,
                'endpoint' => $this->apiEndpoint
            ]);
            // Coba parse error dari Gemini jika ada
            $apiErrorDetails = $response->json();
            $errorMessage = 'Failed to call Gemini API. Status: ' . $response->status();
            if (isset($apiErrorDetails['error']['message'])) {
                $errorMessage .= ' Details: ' . $apiErrorDetails['error']['message'];
            } else {
                $errorMessage .= ' Body: ' . $errorBody;
            }
            throw new Exception($errorMessage);
        }

        // Ekstrak teks dari respons
        // Struktur respons Gemini Vision bisa sedikit berbeda, pastikan path ini sesuai
        // 'candidates'[0]['content']['parts'][0]['text']
        $responseText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($responseText === null) { // Bisa jadi respons sukses tapi tidak ada 'text' (jarang terjadi jika prompt valid)
            // Periksa apakah ada 'finishReason' seperti 'SAFETY'
            $finishReason = $response->json()['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            if ($finishReason === 'SAFETY') {
                Log::warning('Gemini API call blocked due to safety reasons.', ['response' => $response->json()]);
                throw new Exception('Gemini API request was blocked due to safety settings.');
            } elseif (isset($response->json()['candidates'][0]['content']['parts']) && empty($response->json()['candidates'][0]['content']['parts'])) {
                // Ini berarti API mengembalikan 'parts' array kosong, yang bisa diartikan tidak ada deteksi yang valid
                // atau model tidak menghasilkan output teks. Untuk kasus kita (meminta JSON list),
                // ini bisa berarti "empty JSON list []" adalah output yang valid jika prompt dihandle dengan baik oleh model.
                // Kita akan tangani ini di tahap parsing.
                Log::info('Gemini API response had no text part, but was successful. Potentially an empty detection.', ['response' => $response->json()]);
                // Jika model mengembalikan array kosong "[]" sebagai output teks valid, parsing akan menghasilkan array kosong.
            } else {
                Log::error('Gemini response did not contain expected text part.', ['response' => $response->json()]);
                throw new Exception('Gemini response structure error: text part missing.');
            }
        }

        // Jika responseText adalah string kosong atau hanya whitespace, dan kita mengharapkan JSON list,
        // ini bisa dianggap sebagai "tidak ada deteksi yang valid" atau output yang tidak diharapkan.
        // Namun, model mungkin valid mengembalikan "[]" sebagai teks.
        if (trim($responseText ?? '') === '') {
            // Periksa jika prompt kita secara eksplisit meminta "[]" untuk 'no items found'
            // Jika ya, maka ini adalah hasil yang valid.
            Log::info('Gemini API returned an empty string as text part. Assuming empty detection list as per prompt design.', ['response' => $response->json()]);
            $responseText = '[]'; // Asumsikan ini sebagai daftar kosong jika prompt kita mendukungnya.
        }


        return $this->parseGeminiResponse($responseText);
    }

    /**
     * Mem-parsing respons JSON mentah dari Gemini.
     * (Menghilangkan markdown, dll.)
     *
     * @param string|null $responseText
     * @return array
     * @throws Exception
     */
    protected function parseGeminiResponse(?string $responseText): array
    {
        if ($responseText === null || trim($responseText) === '') {
            // Jika setelah semua pengecekan di callGeminiApi, responseText masih null atau kosong,
            // dan kita berharap JSON, ini adalah masalah. Namun, jika prompt kita bisa menghasilkan "[]",
            // maka ini bisa jadi valid.
            // Kita sudah handle ini dengan men-set $responseText = '[]' di atas.
            Log::info('Parsing an empty or null responseText as an empty list.');
            return []; // Kembalikan array kosong jika tidak ada teks atau teksnya kosong.
        }

        $jsonString = $responseText;

        // Menghilangkan ```json ... ``` jika ada (model kadang masih menyertakannya)
        if (strpos($jsonString, '```json') !== false) {
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $jsonString, $matches)) {
                $jsonString = $matches[1];
            } else {
                // Fallback jika regex tidak cocok tapi ```json ada
                $jsonString = str_replace(['```json', '```'], '', $jsonString);
            }
        }
        // Juga hilangkan ``` saja jika hanya itu yang ada
        $jsonString = str_replace('```', '', trim($jsonString));


        try {
            $parsedResponse = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            // Pastikan hasilnya adalah array (list)
            if (!is_array($parsedResponse)) {
                // Jika hasil parse bukan array (misalnya string atau objek tunggal padahal kita minta list)
                // dan $jsonString sebenarnya adalah '[]', maka $parsedResponse akan jadi array kosong, itu valid.
                // Tapi jika $jsonString bukan '[]' dan hasilnya bukan array, itu masalah.
                if ($jsonString === '[]' && is_array($parsedResponse)) { // ini kondisi valid
                    return $parsedResponse;
                }
                Log::warning('Parsed Gemini response is not a list (array).', ['original_text' => $responseText, 'parsed_string' => $jsonString, 'parsed_result' => $parsedResponse]);
                // Jika kita selalu mengharapkan list, ini bisa dianggap error atau kita kembalikan list kosong.
                // throw new Exception('Gemini response, after parsing, was not in the expected list format.');
                return []; // Atau kembalikan array kosong jika ini bisa terjadi
            }
            return $parsedResponse; // Seharusnya ini adalah list (array PHP) dari objek deteksi
        } catch (\JsonException $e) {
            Log::error('Failed to parse JSON response from Gemini:', [
                'error' => $e->getMessage(),
                'original_text' => $responseText,
                'processed_string_for_json_decode' => $jsonString
            ]);
            throw new Exception('Failed to parse JSON response from Gemini: ' . $e->getMessage());
        }
    }

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
}
