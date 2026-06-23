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

        // Cek apakah session sudah ada
        $existing = WhatsAppSession::where('name', $request->name)->first();
        if ($existing) {
            return response()->json(['message' => 'Session name already exists'], 422);
        }

        $session = WhatsAppSession::create([
            'name' => $request->name,
            'status' => 'qr',
            'last_active' => now()
        ]);

        // Kirim request ke Baileys service (yang jalan di port 3000)
        try {
            $response = Http::timeout(15)->post('http://localhost:3000/start-session', [
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
            // Tetap simpan session meski Baileys belum respond
            \Log::error('Baileys connection failed: ' . $e->getMessage());
        }

        return response()->json($session);
    }

    public function show($id)
    {
        return WhatsAppSession::findOrFail($id);
    }
}