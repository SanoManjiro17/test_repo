
let absenModal;

document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Modal Bootstrap
    const modalEl = document.getElementById('absenModal');
    if (modalEl) {
        absenModal = new bootstrap.Modal(modalEl);
        
        // Tampilkan modal absen otomatis saat halaman dimuat jika belum absen dan tidak sedang izin
        if (typeof dashboardConfig !== 'undefined' && dashboardConfig.shouldShowAbsenModal) {
            absenModal.show();
        }
    }

    // Tampilkan pesan sukses dari session jika ada
    if (typeof dashboardConfig !== 'undefined' && dashboardConfig.absenSuccessMessage) {
        showNotifikasi(dashboardConfig.absenSuccessMessage);
    }
    
    // Session timeout monitoring (29 menit - 1 menit sebelum timeout)
    let sessionTimeout;
    const SESSION_TIMEOUT = 29 * 60 * 1000; // 29 menit dalam ms
    
    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(() => {
            if (confirm('Session Anda akan segera habis. Klik OK untuk tetap login.')) {
                // Kirim request untuk memperbarui session
                fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=keep_session'
                }).then(response => {
                    if (response.ok) {
                        resetSessionTimeout();
                    } else {
                        window.location.href = 'login.php?timeout=1';
                    }
                }).catch(() => {
                    window.location.href = 'login.php?timeout=1';
                });
            } else {
                window.location.href = 'logout.php';
            }
        }, SESSION_TIMEOUT);
    }
    
    // Reset timeout pada setiap aktivitas
    document.addEventListener('click', resetSessionTimeout);
    document.addEventListener('keypress', resetSessionTimeout);
    document.addEventListener('mousemove', resetSessionTimeout);
    
    // Mulai monitoring
    resetSessionTimeout();
});

// Fungsi untuk membuka modal absen masuk
function absenMasuk() {
    if (absenModal) {
        absenModal.show();
    }
}

// Fungsi untuk menampilkan modal izin/sakit
function showIzinModal(type) {
    const title = type === 'sakit' ? 'Pengajuan Izin Sakit' : 'Pengajuan Izin Cuti';
    const label = type === 'sakit' ? 'Bukti Surat Dokter (Opsional)' : 'Dokumen Pendukung / Form Cuti (Opsional)';
    const modalTitle = document.getElementById('izinModalTitle');
    const tipeIzin = document.getElementById('tipe_izin');
    const labelBukti = document.getElementById('label_bukti');
    
    if(modalTitle) modalTitle.innerText = title;
    if(tipeIzin) tipeIzin.value = type;
    if(labelBukti) labelBukti.innerText = label;
    
    // Tampilkan upload file untuk keduanya
    const fileContainer = document.getElementById('file_sakit_container');
    if(fileContainer) fileContainer.style.display = 'block';
    
    const modalEl = document.getElementById('modalIzin');
    if(modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

// Fungsi konfirmasi absen masuk dari dalam modal
async function confirmAbsenMasuk() {
    const btn = document.getElementById('btn-confirm-checkin');
    if(!btn) return;
    
    const originalText = btn.innerHTML;
    
    try {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> memproses...';
        btn.disabled = true;
        
        const response = await fetch('dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=absen_masuk_ajax'
        });
        
        const data = await response.json();
        if (data.success) {
            if (absenModal) absenModal.hide();
            
            showNotifikasi(data.message, 'success');
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotifikasi('Gagal: ' + data.message, 'danger');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        showNotifikasi('Terjadi kesalahan koneksi.', 'danger');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// Fungsi AJAX untuk absen keluar
async function absenKeluar() {
    if (!confirm('Apakah Anda ingin melakukan Absen Keluar sekarang?')) return;
    
    const btn = document.getElementById('btn-absen-keluar');
    
    try {
        if(btn) {
            btn.innerText = 'Memproses...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.7';
        }
        
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=absen_keluar_ajax'
        });
        
        const data = await response.json();
        if (data.success) {
            showNotifikasi(data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotifikasi('Gagal: ' + data.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotifikasi('Terjadi kesalahan koneksi.', 'danger');
    }
}

// Fungsi AJAX Mulai Lembur
async function mulaiLembur() {
    if (!confirm('Mulai lembur sekarang?')) return;
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mulai_lembur_ajax'
        });
        const data = await response.json();
        if (data.success) {
            showNotifikasi(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotifikasi(data.message, 'danger');
        }
    } catch (error) {
        showNotifikasi('Kesalahan koneksi', 'danger');
    }
}

// Fungsi AJAX Selesai Lembur
async function selesaiLembur() {
    if (!confirm('Selesaikan lembur sekarang?')) return;
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=selesai_lembur_ajax'
        });
        const data = await response.json();
        if (data.success) {
            showNotifikasi(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotifikasi(data.message, 'danger');
        }
    } catch (error) {
        showNotifikasi('Kesalahan koneksi', 'danger');
    }
}

// Waktu server
let serverTime;
let localTimeAtStart = new Date();
let serverTimezone = 'Asia/Jakarta';

if (typeof dashboardConfig !== 'undefined') {
    serverTime = new Date(dashboardConfig.serverTime);
    serverTimezone = dashboardConfig.serverTimezone;
} else {
    serverTime = new Date();
}

function updateJam() {
    const jamEl = document.getElementById('jam_sekarang');

    if (jamEl) {
        // Hitung selisih waktu server dengan waktu lokal sejak halaman dimuat
        const now = new Date();
        const elapsed = now.getTime() - localTimeAtStart.getTime();
        const currentServerTime = new Date(serverTime.getTime() + elapsed);
        
        const jam = currentServerTime.getHours().toString().padStart(2, '0');
        const menit = currentServerTime.getMinutes().toString().padStart(2, '0');
        const detik = currentServerTime.getSeconds().toString().padStart(2, '0');
        
        // Selalu tampilkan jam real-time
        jamEl.textContent = `${jam}:${menit}:${detik}`;
        
        // Update jam masuk otomatis
        const jamMasukOtomatis = document.getElementById('jam_masuk_otomatis');
        if (jamMasukOtomatis) {
            jamMasukOtomatis.textContent = `${jam}:${menit}:${detik}`;
        }
    }
    
    // Update info timezone
    const serverTimezoneEl = document.getElementById('server_timezone');
    if (serverTimezoneEl) {
        serverTimezoneEl.textContent = serverTimezone;
    }
}

// Fungsi untuk sinkronisasi waktu server
function syncServerTime() {
    fetch('dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_server_time'
    })
        .then(response => response.json())
        .then(data => {
            if(data.server_time) {
                // Kita perlu tanggal juga untuk membuat Date object yang benar
                const dateTimeStr = data.server_date + ' ' + data.server_time;
                serverTime = new Date(dateTimeStr);
                localTimeAtStart = new Date(); // Reset local reference
            }
        })
        .catch(error => {
            console.log('Gagal sinkronisasi waktu server, menggunakan waktu lokal');
        });
}

// Sinkronisasi awal dan setiap 5 menit
updateJam();
setInterval(updateJam, 1000);
setInterval(syncServerTime, 300000); // Sinkronisasi setiap 5 menit

// Sinkronisasi pertama kali setelah 2 detik
setTimeout(syncServerTime, 2000);

// Fungsi untuk menampilkan notifikasi toast (hijau, pojok kiri atas)
function showNotifikasi(pesan, tipe = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast-custom';
    if (tipe === 'danger') toast.style.background = '#e74c3c';
    
    toast.innerHTML = `
        <span>${pesan}</span>
        <span class="close-toast" onclick="this.parentElement.remove()">x</span>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove setelah 5 detik
    setTimeout(() => {
        if (toast.parentNode) toast.remove();
    }, 5000);
}
