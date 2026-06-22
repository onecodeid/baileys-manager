<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use Illuminate\Http\Request;

class WhatsAppSessionController extends Controller
{
    public function index()
    {
        return response()->json(WhatsAppSession::all());
    }

    public function store(Request $request)
    {
        $session = WhatsAppSession::create([
            'name' => $request->name,
            'status' => 'qr'
        ]);

        // Trigger Node.js Baileys via HTTP atau Socket (nanti dijelaskan)
        return response()->json($session);
    }

    public function show($id)
    {
        return WhatsAppSession::findOrFail($id);
    }
}
