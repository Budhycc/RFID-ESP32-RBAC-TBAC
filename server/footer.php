        </main>
    </div>
</div>

<footer style="margin-top: auto; padding: 20px 30px; text-align: center; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border-color); background-color: var(--bg-card);">
    &copy; <?= date("Y") ?> Sistem Kontrol Akses Ruangan Berbasis RFID. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".timepicker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });
    }
</script>

</body>
</html>
