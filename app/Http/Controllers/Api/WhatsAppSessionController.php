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
            'name' => $request->name,
            'status' => 'qr',
            'last_active' => now()
        ]);

        // Panggil Baileys yang ada di localhost:3000 (internal)
        try {
            $response = Http::timeout(20)
                ->post('http://127.0.0.1:3000/start-session', [
                    'session_name' => $request->name
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $session->update([
                    'qr_code' => $data['qr_code'] ?? null,
                    'status' => $data['status'] ?? 'qr'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Baileys Error: ' . $e->getMessage());
        }

        return response()->json($session);
    }
}