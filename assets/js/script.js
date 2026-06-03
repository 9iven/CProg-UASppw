document.addEventListener("DOMContentLoaded", function() {
    // 1. Mencegah user menekan tombol submit berkali-kali pada form
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        form.addEventListener("submit", function() {
            const submitBtn = this.querySelector("button[type='submit']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = "Memproses...";
                submitBtn.style.opacity = "0.7";
            }
        });
    });

    // 2. Animasi sederhana untuk notifikasi alert agar hilang perlahan
    const alerts = document.querySelectorAll(".alert-success, .alert-error");
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 500);
        }, 4000); // Menghilang setelah 4 detik
    });
});