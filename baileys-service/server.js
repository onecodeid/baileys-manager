const express = require('express');
const { default: makeWASocket, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());

const AUTH_DIR = path.join(__dirname, '../auth');
if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
}

console.log("🚀 Baileys Server Starting...");

app.post('/start-session', async (req, res) => {
    const { session_name } = req.body;

    if (!session_name) {
        return res.status(400).json({ error: "session_name is required" });
    }

    console.log(`🔄 [${session_name}] Starting session...`);

    try {
        const { state, saveCreds } = await useMultiFileAuthState(`../auth/${session_name}`);

        const sock = makeWASocket({
            auth: state,
            printQRInTerminal: true,
            // logger: console,   // <-- di-comment untuk menghindari error
        });

        let qrCodeBase64 = null;

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                try {
                    qrCodeBase64 = await QRCode.toDataURL(qr);
                    console.log(`✅ [${session_name}] QR Code Generated`);
                } catch (e) {
                    console.log(`⚠️ Failed to generate QR for ${session_name}`);
                }
            }

            if (connection === 'open') {
                console.log(`✅ [${session_name}] WhatsApp CONNECTED SUCCESSFULLY!`);
            }

            if (connection === 'close') {
                console.log(`❌ [${session_name}] Connection closed`);
            }
        });

        sock.ev.on('creds.update', saveCreds);

        // Response cepat
        res.json({
            status: 'success',
            message: 'Session started',
            qr_code: qrCodeBase64
        });

    } catch (error) {
        console.error(`💥 [${session_name}] Error:`, error.message);
        res.status(500).json({ error: error.message });
    }
});

const PORT = 3000;
app.listen(PORT, '127.0.0.1', () => {
    console.log(`✅ Baileys Server running on http://127.0.0.1:${PORT}`);
});
