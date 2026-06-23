const express = require('express');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');
const P = require('pino');  // ⬅️ pino sudah include di baileys, tidak perlu install

const app = express();
app.use(express.json());

const AUTH_DIR = path.join(__dirname, 'auth');
if (!fs.existsSync(AUTH_DIR)) fs.mkdirSync(AUTH_DIR, { recursive: true });

const sessions = {};

console.log("🚀 Baileys Server Starting...");

app.post('/start-session', async (req, res) => {
  const { session_name } = req.body;
  if (!session_name) return res.status(400).json({ error: "session_name is required" });

  if (sessions[session_name]?.status === 'connected') {
    return res.json({ status: 'already_connected', message: `Session ${session_name} sudah terhubung` });
  }

  sessions[session_name] = { sock: null, qr_code: null, status: 'connecting' };
  res.json({ status: 'success', message: 'Session dimulai, poll /get-qr untuk QR code' });

  _initSocket(session_name);
});

async function _initSocket(session_name) {
  try {
    const { state, saveCreds } = await useMultiFileAuthState(
      path.join(AUTH_DIR, session_name)
    );

    const sock = makeWASocket({
      auth: state,
      // ⬅️ HAPUS printQRInTerminal, ganti dengan silent logger
      logger: P({ level: 'silent' }),
    });

    sessions[session_name].sock = sock;

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        try {
          const qrBase64 = await QRCode.toDataURL(qr);
          sessions[session_name].qr_code = qrBase64;
          sessions[session_name].status = 'qr_ready';
          console.log(`✅ [${session_name}] QR Code ready`);
        } catch (e) {
          console.error(`⚠️ [${session_name}] Gagal generate QR:`, e.message);
        }
      }

      if (connection === 'open') {
        sessions[session_name].status = 'connected';
        sessions[session_name].qr_code = null;
        console.log(`✅ [${session_name}] WhatsApp CONNECTED!`);
      }

      if (connection === 'close') {
        const code = lastDisconnect?.error?.output?.statusCode;
        const shouldReconnect = code !== DisconnectReason.loggedOut;
        sessions[session_name].status = 'closed';
        console.log(`❌ [${session_name}] Connection closed (code: ${code})`);

        if (shouldReconnect) {
          console.log(`🔁 [${session_name}] Reconnecting in 3s...`);
          setTimeout(() => _initSocket(session_name), 3000);
        } else {
          delete sessions[session_name];
        }
      }
    });

    sock.ev.on('creds.update', saveCreds);

  } catch (error) {
    console.error(`💥 [${session_name}] Init error:`, error.message);
    if (sessions[session_name]) sessions[session_name].status = 'error';
    // ⬅️ TIDAK throw error — jangan sampai crash server
  }
}

app.get('/get-qr', (req, res) => {
  const { session_name } = req.query;
  if (!session_name) return res.status(400).json({ error: "session_name is required" });

  const session = sessions[session_name];
  if (!session) {
    return res.status(404).json({ error: "Session tidak ditemukan, panggil /start-session dulu" });
  }

  res.json({
    status: session.status,
    qr_code: session.qr_code ?? null,
  });
});

app.get('/status', (req, res) => {
  const { session_name } = req.query;
  if (!session_name) return res.status(400).json({ error: "session_name is required" });
  const session = sessions[session_name];
  res.json({ status: session?.status ?? 'not_found' });
});

app.post('/send-message', async (req, res) => {
  const { session_name, to, message } = req.body;
  if (!session_name || !to || !message) {
    return res.status(400).json({ error: "session_name, to, message wajib diisi" });
  }
  const session = sessions[session_name];
  if (!session || session.status !== 'connected') {
    return res.status(400).json({ error: "Session belum terhubung" });
  }
  try {
    const jid = to.includes('@') ? to : `${to}@s.whatsapp.net`;
    await session.sock.sendMessage(jid, { text: message });
    res.json({ status: 'success', message: 'Pesan terkirim' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

const PORT = 3000;
app.listen(PORT, '127.0.0.1', () => {
  console.log(`✅ Baileys Server running on http://127.0.0.1:${PORT}`);
});