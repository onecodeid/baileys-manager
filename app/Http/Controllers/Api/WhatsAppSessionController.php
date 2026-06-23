<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppSessionController extends Controller
{
    public function index()
    {
        return WhatsAppSession::latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:whats_app_sessions,name'
        ]);

        $session = WhatsAppSession::create([
            'name'        => $request->name,
            'status'      => 'connecting',
            'last_active' => now()
        ]);

        // Fire start-session ke Baileys (respond cepat, QR belum ada)
        try {
            Http::timeout(10)->post('http://127.0.0.1:3000/start-session', [
                'session_name' => $request->name
            ]);
        } catch (\Exception $e) {
            \Log::error('Baileys start-session Error: ' . $e->getMessage());
            return response()->json($session); // tetap return, polling akan handle
        }

        // Poll /get-qr sampai QR ready (max 15 detik)
        $qrCode = null;
        for ($i = 0; $i < 10; $i++) {
            sleep(2);

            try {
                $qrRes = Http::timeout(5)
                    ->get('http://127.0.0.1:3000/get-qr', [
                        'session_name' => $request->name
                    ]);

                $qrData = $qrRes->json();

                if (!empty($qrData['qr_code'])) {
                    $qrCode = $qrData['qr_code'];
                    break;
                }

                // Kalau sudah connected langsung (auth tersimpan sebelumnya)
                if (($qrData['status'] ?? '') === 'connected') {
                    $session->update(['status' => 'connected', 'last_active' => now()]);
                    return response()->json($session->fresh());
                }
            } catch (\Exception $e) {
                \Log::warning('Baileys poll QR error: ' . $e->getMessage());
            }
        }

        // Update DB dengan QR (atau tetap null kalau timeout)
        $session->update([
            'qr_code' => $qrCode,
            'status'  => $qrCode ? 'qr' : 'connecting',
        ]);

        return response()->json($session->fresh());
    }

    // Endpoint untuk frontend polling QR (dipanggil Vue setiap 3 detik)
    public function getQr(Request $request)
    {
        $session = WhatsAppSession::where('name', $request->session_name)->firstOrFail();

        // Tanya Baileys langsung untuk status terbaru
        try {
            $res = Http::timeout(5)->get('http://127.0.0.1:3000/get-qr', [
                'session_name' => $session->name
            ]);
            $data = $res->json();

            // Sync ke DB kalau ada update
            $update = [];
            if (!empty($data['qr_code']))          $update['qr_code'] = $data['qr_code'];
            if (!empty($data['status']))            $update['status']  = $data['status'] === 'connected' ? 'connected' : ($data['qr_code'] ? 'qr' : $session->status);
            if ($data['status'] === 'connected')    $update['last_active'] = now();

            if ($update) $session->update($update);

        } catch (\Exception $e) {
            \Log::warning('getQr poll error: ' . $e->getMessage());
        }

        return response()->json($session->fresh());
    }
}