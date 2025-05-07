<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver; // Atau Imagick jika Anda prefer dan sudah terinstall
use Intervention\Image\ImageManager;
use Exception; // Import class Exception

class GeminiVisionService
{
    protected string $apiKey;
    protected string $apiEndpoint;

    public function __construct()
    {
        // Ambil API Key dan Endpoint dari config/services.php
        // Pastikan Anda sudah mengkonfigurasi ini dengan benar dan merujuk ke GEMINI_API_ENDPOINT_FLASH
        $this->apiKey = config('services.google.api_key');
        $this->apiEndpoint = config('services.google.api_endpoint_flash'); // Menggunakan endpoint flash

        if (!$this->apiKey || !$this->apiEndpoint) {
            Log::error('Gemini API Key or Endpoint is not configured in services.php.');
            // Anda bisa throw exception di sini jika ingin menghentikan proses jika tidak terkonfigurasi
            // throw new \Exception('Gemini API Key or Endpoint is not configured.');
        }
    }

    /**
     * Analyze an image using Google Gemini Vision API.
     *
     * @param \Illuminate\Http\UploadedFile|\Symfony\Component\HttpFoundation\File\File|string $imageInput Path to image, UploadedFile object, or base64 string
     * @param string $targetPrompt Specific items to look for in the image.
     * @param string $labelPrompt How to describe the items found.
     * @return array Parsed response from Gemini or throws an Exception on failure.
     * @throws \Exception
     */
    public function analyzeImage($imageInput, string $targetPrompt, string $labelPrompt): array
    {
        if (!$this->apiKey || !$this->apiEndpoint) {
            throw new \Exception('Gemini API Key or Endpoint is not configured.');
        }

        $imageBase64 = null;
        $imageMimeType = null;

        try {
            // === Langkah Resizing (Intervention Image v3) ===
            $manager = new ImageManager(new Driver()); // Gunakan Driver GD atau Imagick
            $img = null;

            if ($imageInput instanceof \Illuminate\Http\UploadedFile || $imageInput instanceof \Symfony\Component\HttpFoundation\File\File) {
                $img = $manager->read($imageInput->getRealPath());
                $imageMimeType = $imageInput->getMimeType(); // Dapatkan mime type asli
            } elseif (is_string($imageInput) && file_exists($imageInput)) { // Jika path file
                $img = $manager->read($imageInput);
                $imageMimeType = mime_content_type($imageInput);
            } elseif (is_string($imageInput) && str_starts_with($imageInput, 'data:image')) { // Jika data URI base64
                // Handle base64 input jika diperlukan, atau minta UploadedFile/path
                // Untuk sekarang, kita fokus pada file input
                throw new \Exception('Base64 image input processing not fully implemented in this example. Please provide a file path or UploadedFile object.');
            } else {
                throw new \Exception('Invalid image input type.');
            }

            // Resize gambar
            $img->resize(400, 400); // Atau ->cover(400, 400) atau ->fit(400, 400)

            // Tentukan mime type untuk encoding, default ke jpeg jika tidak terdeteksi
            $outputMimeType = 'image/jpeg';
            if (in_array(strtolower($imageMimeType ?? ''), ['image/png', 'image/webp'])) {
                // $outputMimeType = strtolower($imageMimeType); // Jika ingin mempertahankan PNG/WebP
                // Jika tetap JPEG untuk konsistensi ukuran request
                $encodedImage = $img->toJpeg(90); // Kualitas 90%
            } else { // Default ke JPEG
                $encodedImage = $img->toJpeg(90);
            }
            $imageBase64 = base64_encode($encodedImage);
            // === Akhir Resizing ===

        } catch (\Exception $e) {
            Log::error('Intervention Image processing failed:', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to process the uploaded image. ' . $e->getMessage());
        }

        if (empty($imageBase64)) { // Mime type tidak perlu dicek di sini karena sudah di-handle $outputMimeType
            throw new \Exception('Image data could not be prepared for the API.');
        }

        // === Siapkan Prompt & Panggil Gemini API ===
        // Contoh prompt dari diskusi sebelumnya
        // $targetPrompt = "bottles, identifying if they are mineral water bottles or other types (like soda, tea, coffee bottles), and whether they appear empty or filled (note potential contents like water, cigarette butts, sticks of wood, etc if visible)";
        // $labelPrompt = "a label describing the bottle type and fill status (e.g., 'empty mineral bottle', 'filled soda bottle - water', 'filled tea bottle - trash')";
        $fullPrompt = "Detect {$targetPrompt}, with no more than 20 items. Output ONLY a valid JSON list (no extra text or markdown formatting) where each entry contains the 2D bounding box in \"box_2d\" (as [ymin, xmin, ymax, xmax] scaled 0-1000) and {$labelPrompt} in \"label\".";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt],
                        ['inline_data' => [
                            'mime_type' => $outputMimeType, // Gunakan outputMimeType
                            'data' => $imageBase64
                        ]]
                    ]
                ]
            ],
            // Anda bisa menambahkan generationConfig di sini jika perlu
            // 'generationConfig' => [
            //     'candidateCount' => 1,
            //     'maxOutputTokens' => 2048,
            //     'temperature' => 0.5, // Sesuaikan untuk kreativitas vs faktual
            //     'topP' => 1,
            //     'topK' => 32,
            // ]
        ];

        $response = Http::timeout(60) // Timeout 60 detik
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->apiEndpoint . '?key=' . $this->apiKey, $payload);

        if (!$response->successful()) {
            Log::error('Gemini API Error:', ['status' => $response->status(), 'body' => $response->body()]);
            $apiErrorDetails = $response->json() ?? $response->body();
            throw new \Exception('Failed to call Gemini API. Status: ' . $response->status() . ' Details: ' . json_encode($apiErrorDetails));
        }

        // Parsing JSON response dari Gemini
        // Teks respons mungkin berada di dalam 'candidates'[0]['content']['parts'][0]['text']
        $responseText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$responseText) {
            Log::warning('Gemini response did not contain expected text part.', ['response_body' => $response->body()]);
            throw new \Exception('Gemini response did not contain expected text part or the structure is different.');
        }

        // Menghilangkan ```json dan ``` dari respons
        $jsonString = $responseText;
        if (strpos($responseText, '```json') !== false) {
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $responseText, $matches)) {
                $jsonString = $matches[1];
            } else {
                // Fallback jika regex gagal tapi ```json ada
                $jsonString = str_replace(['```json', '```'], '', $responseText);
            }
        }

        try {
            $parsedResponse = json_decode(trim($jsonString), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Failed to parse JSON response from Gemini:', ['raw_text' => $responseText, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to parse JSON response from Gemini. Raw text: ' . $responseText);
        }

        // Di sini, $parsedResponse adalah array PHP dari JSON yang dikembalikan Gemini.
        // Anda mungkin perlu melakukan format ulang atau validasi tambahan di sini atau di controller yang memanggil service ini.
        // Contoh: Format Bounding Box jika diperlukan
        // $formattedBoxes = [];
        // if (is_array($parsedResponse)) {
        //     foreach ($parsedResponse as $box) {
        //         if (isset($box['box_2d']) && is_array($box['box_2d']) && count($box['box_2d']) === 4 && isset($box['label'])) {
        //             [$ymin, $xmin, $ymax, $xmax] = $box['box_2d'];
        //             $formattedBoxes[] = [
        //                 'x' => $xmin / 1000, 
        //                 'y' => $ymin / 1000, 
        //                 'width' => ($xmax - $xmin) / 1000, 
        //                 'height' => ($ymax - $ymin) / 1000, 
        //                 'label' => $box['label']
        //             ];
        //         } else {
        //             Log::warning('Skipping invalid box data from Gemini in service:', ['box_data' => $box]);
        //         }
        //     }
        // } else {
        //      Log::warning('Parsed Gemini response is not an array as expected.', ['parsed_response' => $parsedResponse]);
        // }
        // return ['boundingBoxes' => $formattedBoxes];

        return $parsedResponse; // Kembalikan array yang sudah diparsing
    }
}
