<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process; // Untuk menjalankan skrip shell
use Illuminate\Support\Str; // Untuk Str::startsWith

class WebhookController extends Controller
{
    public function handleGithub(Request $request)
    {
        // 1. Verifikasi Signature (PENTING untuk Keamanan)
        $githubSignature = $request->header('X-Hub-Signature-256');
        $secret = env('GITHUB_WEBHOOK_SECRET'); // Ambil dari .env

        if (empty($secret)) {
            Log::error('GITHUB WEBHOOK SECRET NOT SET IN in .env');
            return response()->json(['message' => 'Webhook secret not configured on server.'], 500);
        }

        if (empty($githubSignature)) {
            Log::warning('Webhook call missing X-Hub-Signature-256 header.');
            return response()->json(['message' => 'Signature header missing.'], 400);
        }

        $payloadBody = $request->getContent();
        $hash = 'sha256=' . hash_hmac('sha256', $payloadBody, $secret, false);

        if (!hash_equals($hash, $githubSignature)) {
            Log::warning('Webhook signature mismatch.', ['received_signature' => $githubSignature]);
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        // 2. Verifikasi Event (Pastikan hanya event 'push' yang diproses)
        $githubEvent = $request->header('X-GitHub-Event');
        Log::info('GitHub Webhook Event Received:', ['event' => $githubEvent]);

        if ($githubEvent === 'ping') {
            Log::info('GitHub Webhook: Ping event received successfully.');
            return response()->json(['message' => 'pong']);
        }

        if ($githubEvent !== 'push') {
            Log::info('GitHub Webhook: Ignoring event.', ['event' => $githubEvent]);
            return response()->json(['message' => 'Event ignored.']);
        }

        // 3. (Opsional) Verifikasi Branch (jika Anda hanya ingin update dari branch tertentu)
        $payload = $request->json()->all();
        $ref = $payload['ref'] ?? '';
        Log::info('GitHub Webhook: Push event details.', ['ref' => $ref]);

        // Contoh: Hanya update jika push ke branch 'main' atau 'master'
        if (!Str::endsWith($ref, '/main') && !Str::endsWith($ref, '/master')) {
            Log::info('GitHub Webhook: Push event not for main/master branch. Ignoring.', ['ref' => $ref]);
            return response()->json(['message' => 'Push not to main/master branch.']);
        }

        // 4. Jalankan Skrip Update
        $scriptPath = base_path('update_app.sh'); // Menggunakan base_path() untuk path absolut ke root proyek

        if (!file_exists($scriptPath)) {
            Log::error('Update script not found at path: ' . $scriptPath);
            return response()->json(['message' => 'Update script not found on server.'], 500);
        }

        Log::info('GitHub Webhook: Attempting to execute update script.', ['script' => $scriptPath]);

        // Jalankan skrip di background agar request webhook cepat kembali
        // Redirect output ke file log agar bisa dicek
        $logFile = storage_path('logs/webhook_update.log');
        $process = new Process(['bash', $scriptPath]);
        $process->setWorkingDirectory(base_path()); // Set working directory ke root proyek
        $process->disableOutput(); // Kita akan log manual jika perlu atau output ke file
        // $process->run(); // Untuk menjalankan secara sinkron (webhook akan menunggu)

        // Untuk menjalankan secara asinkron (lebih baik untuk webhook)
        try {
            $process->start();
            Log::info('GitHub Webhook: Update script started in background.', ['pid' => $process->getPid()]);
            // Anda bisa menyimpan PID jika perlu memantau prosesnya, tapi untuk sekarang cukup log
        } catch (\Exception $e) {
            Log::error('GitHub Webhook: Failed to start update script.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to start update script.'], 500);
        }


        // Beri tahu GitHub bahwa request diterima dan sedang diproses
        return response()->json(['message' => 'Webhook received. Update process initiated.'], 202);
    }
}