<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">📱 Bailey WhatsApp Manager</h2>
      <div>
        <button class="btn btn-success me-2" @click="showAddModal = true">
          + Register New Number
        </button>
        <button class="btn btn-outline-danger" onclick="logout()">Logout</button>
      </div>
    </div>

    <!-- Session List -->
    <div class="card">
      <div class="card-header bg-dark text-white">
        <strong>All Registered Sessions</strong>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th width="50">#</th>
              <th>Session Name</th>
              <th>Phone Number</th>
              <th>Status</th>
              <th>Last Active</th>
              <th width="150">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(session, index) in sessions" :key="session.id">
              <td>{{ index + 1 }}</td>
              <td><strong>{{ session.name }}</strong></td>
              <td>{{ session.phone_number || '-' }}</td>
              <td>
                <span :class="getStatusClass(session.status)">
                  {{ session.status.toUpperCase() }}
                </span>
              </td>
              <td>{{ session.last_active ? formatDate(session.last_active) : '-' }}</td>
              <td>
                <button 
                  v-if="session.status === 'qr' || session.qr_code"
                  class="btn btn-sm btn-primary"
                  @click="showQR(session)">
                  🔲 Show QR
                </button>
                <button 
                  v-else-if="session.status === 'connected'"
                  class="btn btn-sm btn-success disabled">
                  ✓ Connected
                </button>
              </td>
            </tr>
            <tr v-if="sessions.length === 0">
              <td colspan="6" class="text-center py-4 text-muted">
                Belum ada session. Klik tombol di atas untuk mendaftarkan nomor baru.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add New Session Modal -->
    <div class="modal fade" :class="{ show: showAddModal }" style="display: block;" v-if="showAddModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Register New WhatsApp Number</h5>
            <button type="button" class="btn-close" @click="showAddModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Session Name</label>
              <input 
                v-model="newSession.name" 
                type="text" 
                class="form-control" 
                placeholder="Contoh: Sales Team 1, Admin Utama, dll"
                required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAddModal = false">Cancel</button>
            <button type="button" class="btn btn-primary" @click="registerNewSession" :disabled="!newSession.name">
              Generate QR Code
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" :class="{ show: showQRModal }" style="display: block;" v-if="showQRModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Scan QR Code - {{ currentSession.name }}</h5>
            <button type="button" class="btn-close" @click="closeQRModal"></button>
        </div>
        <div class="modal-body text-center">
            <!-- Loading spinner kalau QR belum ready -->
            <div v-if="!currentQR" class="py-4">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="text-muted">Generating QR Code...</p>
            </div>
            <img v-else :src="currentQR" style="max-width: 320px; height: auto;" class="img-fluid" />
            <p class="mt-3 text-muted">Buka WhatsApp → Link a Device → Scan QR Code ini</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeQRModal">Close</button>
        </div>
        </div>
    </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      sessions: [],
      showAddModal: false,
      showQRModal: false,
      newSession: { name: '' },
      currentSession: {},
      currentQR: '',
      qrPollingInterval: null,   // ⬅️ tambah ini
    }
  },

  async mounted() {
    await axios.get('/sanctum/csrf-cookie');
    this.fetchSessions();
    setInterval(() => this.fetchSessions(), 5000);
  },

  methods: {
    async fetchSessions() {
      try {
        const res = await axios.get('/api/sessions');
        this.sessions = res.data;

        // Sync QR di modal kalau sedang terbuka
        if (this.showQRModal && this.currentSession?.name) {
          const updated = res.data.find(s => s.name === this.currentSession.name);
          if (updated) {
            this.currentQR = updated.qr_code;
            if (updated.status === 'connected') this.closeQRModal();
          }
        }
      } catch (e) {
        console.error(e);
      }
    },

    async registerNewSession() {
      if (!this.newSession.name.trim()) {
        alert("Session Name tidak boleh kosong!");
        return;
      }
      try {
        const res = await axios.post('/api/sessions', this.newSession);
        this.showAddModal = false;
        this.newSession.name = '';

        // Langsung buka QR modal
        this.showQR(res.data);
        this.fetchSessions();
      } catch (e) {
        alert('Gagal membuat session: ' + (e.response?.data?.message || e.message));
      }
    },

    showQR(session) {
      this.currentSession = session;
      this.currentQR = session.qr_code;
      this.showQRModal = true;
      this.startQRPolling(session.name);
    },

    startQRPolling(sessionName) {
      this.stopQRPolling(); // clear interval lama kalau ada
      this.qrPollingInterval = setInterval(async () => {
        try {
          const res = await axios.get(`/api/sessions/${sessionName}/qr`);
          const data = res.data;
          this.currentQR = data.qr_code;

          if (data.status === 'connected') {
            this.closeQRModal();
            alert(`✅ ${sessionName} berhasil terhubung ke WhatsApp!`);
            this.fetchSessions();
          }
        } catch (e) {
          console.error('QR poll error:', e);
        }
      }, 3000);
    },

    stopQRPolling() {
      if (this.qrPollingInterval) {
        clearInterval(this.qrPollingInterval);
        this.qrPollingInterval = null;
      }
    },

    closeQRModal() {
      this.stopQRPolling();
      this.showQRModal = false;
      this.currentQR = '';
    },

    getStatusClass(status) {
      if (status === 'connected') return 'badge bg-success';
      if (status === 'qr')        return 'badge bg-warning text-dark';
      if (status === 'connecting') return 'badge bg-info text-dark';
      return 'badge bg-secondary';
    },

    formatDate(date) {
      return new Date(date).toLocaleString('id-ID');
    }
  },

  // Cleanup interval saat komponen di-destroy
  beforeDestroy() {
    this.stopQRPolling();
  }
}
</script>