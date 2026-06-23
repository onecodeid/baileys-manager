const express = require('express');
const { default: makeWASocket, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const QRCode = require('qrcode');

const app = express();
app.use(express.json());

console.log("✅ Server started successfully");

app.post('/start-session', async (req, res) => {
    console.log("📥 Request received:", req.body);

    const { session_name } = req.body;
    if (!session_name) {
        return res.status(400).json({ error: 'session_name is required' });
    }

    try {
        console.log(`🔄 Starting session: ${session_name}`);

        const { state, saveCreds } = await useMultiFileAuthState(`auth/${session_name}`);

        const sock = makeWASocket({
            auth: state,
            printQRInTerminal: true,
            logger: console,   // tambahan logging
        });

        let qrCode = null;

        sock.ev.on('connection.update', async (update) => {
            const { qr, connection } = update;
            
            if (qr) {
                qrCode = await QRCode.toDataURL(qr);
                console.log(`✅ QR Generated for ${session_name}`);
            }

            if (connection === 'open') {
                console.log(`✅ ${session_name} CONNECTED!`);
            }
        });

        sock.ev.on('creds.update', saveCreds);

        // Response cepat tanpa menunggu QR
        res.json({ 
            status: 'success', 
            message: 'Session started. QR will be generated soon.',
            qr_code: null 
        });

        console.log(`✅ Initial response sent for ${session_name}`);

    } catch (err) {
        console.error("💥 ERROR:", err.message);
        console.error(err.stack);
        res.status(500).json({ error: err.message });
    }
});

const PORT = 3000;
app.listen(PORT, '127.0.0.1', () => {
    console.log(`🚀 Baileys Service running on http://127.0.0.1:${PORT}`);
});
