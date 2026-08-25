#include <ArduinoJson.h>
#include <ESPmDNS.h>
#include <HTTPClient.h>
#include <LiquidCrystal_I2C.h>
#include <MFRC522.h>
#include <Preferences.h>
#include <SPI.h>
#include <WiFi.h>
#include <WiFiManager.h>
#include <WiFiUdp.h>
#include <Wire.h>
#include <esp_mac.h>

// ==========================================
// 1. PIN & KONFIGURASI HARDWARE
// ==========================================

// Inisialisasi Layar LCD I2C (Alamat I2C: 0x27, 16 Kolom x 2 Baris)
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Pin Relay / Solenoid Pintu
#define RELAY_PIN 27
#define RELAY_ON HIGH // HIGH = Solenoid Aktif (Pintu Terbuka)
#define RELAY_OFF LOW // LOW  = Solenoid Mati (Pintu Terkunci)

// Pin Push Button Eksternal pengganti tombol BOOT (GPIO 13)
#define BOOT_BTN_PIN 13

// Durasi Pintu Terbuka setelah Akses Diterima (dalam milidetik)
#define SOLENOID_DELAY_MS 5000

// Pin Komunikasi SPI ESP32 ke Modul RFID RC522
#define SS_PIN 5
#define SCK_PIN 18
#define MISO_PIN 19
#define RST_PIN 4
#define MOSI_PIN 23

// Objek RFID Reader MFRC522
MFRC522 rfid(SS_PIN, RST_PIN);

// Memory NVS (Preferences) untuk menyimpan konfigurasi permanen
Preferences preferences;
String serverUrl;
String deviceID;
bool isRegistrationMode = false;

// Objek WiFiManager & Parameter Custom Web Portal
WiFiManager wm;
WiFiManagerParameter *custom_server_url = nullptr;
WiFiManagerParameter *custom_device = nullptr;
bool shouldSaveConfig = false;

// Global Cache URL Server yang sudah di-resolve
String cachedResolvedUrl = "";
unsigned long lastResolveTime = 0;

// ==========================================
// 2. FUNGSI CALLBACK & KONFIGURASI WIFIMANAGER
// ==========================================

/**
 * @brief Callback saat pengguna mengubah konfigurasi pada portal WiFiManager
 */
void saveConfigCallback() {
  Serial.println(
      "[WiFiManager] Parameter portal diubah. Menandai untuk disimpan...");
  shouldSaveConfig = true;
}

/**
 * @brief Membaca input dari portal Web WiFiManager dan menyimpannya ke memori
 * NVS (Preferences)
 */
void saveParamsCallback() {
  Serial.println(
      "[WiFiManager] Menerima perubahan parameter dari Web Portal...");

  if (custom_server_url == nullptr || custom_device == nullptr)
    return;

  String tempUrl = custom_server_url->getValue();
  tempUrl.trim();
  String tempDevice = custom_device->getValue();
  tempDevice.trim();

  if ((tempUrl.length() > 0 && tempUrl != serverUrl) ||
      (tempDevice.length() > 0 && tempDevice != deviceID)) {
    // Pastikan URL selalu diawali dengan http:// atau https://
    if (tempUrl.length() > 0 && !tempUrl.startsWith("http://") &&
        !tempUrl.startsWith("https://")) {
      tempUrl = "http://" + tempUrl;
    }

    serverUrl = tempUrl;
    deviceID = tempDevice;

    // Simpan ke memori non-volatile (NVS) ESP32
    preferences.begin("config", false);
    preferences.putString("serverUrl", serverUrl);
    preferences.putString("deviceID", deviceID);
    preferences.end();

    Serial.println("[WiFiManager] Pengaturan baru berhasil disimpan di NVS:");
    Serial.println("  - URL Server : " + serverUrl);
    Serial.println("  - Device ID  : " + deviceID);
  }
}

/**
 * @brief Mengatur koneksi WiFi via WiFiManager (Auto-connect & Portal AP jika
 * gagal)
 */
void connectWiFi() {
  WiFi.setAutoReconnect(true);
  wm.setConfigPortalTimeout(120); // Timeout 120 detik jika portal dibuka

  // Tampilan Custom CSS untuk Web Portal WiFiManager (Dark Glassmorphism UI)
  wm.setCustomHeadElement(R"rawhtml(
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap');
  body {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%) !important;
    font-family: 'Outfit', sans-serif !important;
    color: #e2e8f0 !important;
    margin: 0 !important;
    padding: 20px !important;
    min-height: 100vh !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
  }
  .c {
    background: rgba(30, 41, 59, 0.7) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 20px !important;
    padding: 30px !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
    width: 100% !important;
    max-width: 420px !important;
    box-sizing: border-box !important;
    text-align: center !important;
  }
  h1, h2, h3 {
    color: #38bdf8 !important;
    font-weight: 600 !important;
    margin-top: 0 !important;
    margin-bottom: 20px !important;
  }
  input[type="text"], input[type="password"] {
    width: 100% !important;
    padding: 12px 16px !important;
    margin: 8px 0 20px 0 !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    background: rgba(15, 23, 42, 0.6) !important;
    border-radius: 10px !important;
    color: #f1f5f9 !important;
    box-sizing: border-box !important;
  }
  button, input[type="submit"] {
    width: 100% !important;
    padding: 14px !important;
    background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%) !important;
    border: none !important;
    border-radius: 10px !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    cursor: pointer !important;
  }
</style>
)rawhtml");

  if (custom_server_url != nullptr)
    delete custom_server_url;
  if (custom_device != nullptr)
    delete custom_device;

  custom_server_url = new WiFiManagerParameter("server", "URL Server API",
                                               serverUrl.c_str(), 150);
  custom_device =
      new WiFiManagerParameter("device", "Device ID", deviceID.c_str(), 40);

  wm.addParameter(custom_server_url);
  wm.addParameter(custom_device);

  wm.setSaveParamsCallback(saveParamsCallback);
  wm.setSaveConfigCallback(saveConfigCallback);

  bool res = wm.autoConnect("RFID-ACCESS", "12345678");

  if (shouldSaveConfig) {
    saveParamsCallback();
  }

  if (!res) {
    Serial.println(
        "[WiFi] Gagal terhubung WiFi atau portal timeout. Restarting...");
    delay(3000);
    ESP.restart();
  }

  if (!shouldSaveConfig) {
    saveParamsCallback();
  }

  wm.startWebPortal();

  Serial.println("\n================================");
  Serial.println("WiFi Terhubung!");
  Serial.println("SSID     : " + WiFi.SSID());
  Serial.println("IP ESP32 : " + WiFi.localIP().toString());
  Serial.println("Gateway  : " + WiFi.gatewayIP().toString());
  Serial.println("================================");
}

// ==========================================
// 3. FUNGSI JARINGAN & AUTO-DISCOVERY SERVER
// ==========================================

/**
 * @brief Melakukan pemindaian jaringan lokal (Network Sweep) untuk menemukan IP
 * RFID Server secara otomatis
 */
void discoverServerAuto() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Mencari Server");
  lcd.setCursor(0, 1);
  lcd.print("Scanning Net...");

  Serial.println("\n[Network Sweep] Mencari RFID server otomatis...");

  IPAddress localIP = WiFi.localIP();
  String subnet = String(localIP[0]) + "." + String(localIP[1]) + "." +
                  String(localIP[2]) + ".";
  bool found = false;

  for (int i = 1; i <= 254; i++) {
    String targetIP = subnet + String(i);
    if (targetIP == localIP.toString())
      continue;

    if (i % 20 == 0) {
      lcd.setCursor(0, 1);
      lcd.print("IP: *.*.*." + String(i) + "   ");
    }

    WiFiClient client;
    if (client.connect(targetIP.c_str(), 80, 30)) {
      HTTPClient http;
      http.begin(client, "http://" + targetIP + "/tes/api/discover.php");
      int httpCode = http.GET();

      if (httpCode == 200) {
        String payload = http.getString();
        if (payload.indexOf("rfid_server_ok") >= 0) {
          String newUrl = "http://" + targetIP + "/tes/api/scan.php";
          Serial.println("-> RFID Server Ditemukan di IP: " + targetIP);

          if (serverUrl != newUrl) {
            serverUrl = newUrl;
            preferences.begin("config", false);
            preferences.putString("serverUrl", serverUrl);
            preferences.end();
          }

          lcd.clear();
          lcd.setCursor(0, 0);
          lcd.print("Server Ditemukan");
          lcd.setCursor(0, 1);
          lcd.print(targetIP);
          delay(2000);

          found = true;
          http.end();
          client.stop();
          break;
        }
      }
      http.end();
      client.stop();
    }
  }

  if (!found) {
    Serial.println("[Network Sweep] Server tidak ditemukan via sweep. "
                   "Menggunakan URL lama.");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Auto-Scan Gagal");
    delay(1000);
  }
}

/**
 * @brief Menerjemahkan domain mDNS (.local) menjadi IP Address numerik
 * @param url URL asal yang mengandung domain .local
 * @param showLcd Apakah menampilkan progress pencarian di LCD
 * @return String URL dengan IP address yang ter-resolve
 */
String resolveUrlMDNS(String url, bool showLcd = true) {
  int protocolIdx = url.indexOf("://");
  if (protocolIdx == -1)
    return url;

  int hostStart = protocolIdx + 3;
  int hostEnd = url.indexOf("/", hostStart);
  if (hostEnd == -1)
    hostEnd = url.length();

  String hostPort = url.substring(hostStart, hostEnd);
  String host = hostPort;
  String port = "";
  int colonIdx = hostPort.indexOf(":");
  if (colonIdx != -1) {
    host = hostPort.substring(0, colonIdx);
    port = hostPort.substring(colonIdx);
  }

  if (host.endsWith(".local")) {
    String mDnsHost = host.substring(0, host.length() - 6);

    if (showLcd) {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Mencari Server");
      lcd.setCursor(0, 1);
      lcd.print(host);
    }

    IPAddress ip = MDNS.queryHost(mDnsHost.c_str(), 1500);
    if (ip != IPAddress(0, 0, 0, 0)) {
      String resolvedUrl = url.substring(0, hostStart) + ip.toString() + port +
                           url.substring(hostEnd);
      return resolvedUrl;
    } else {
      if (showLcd) {
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Server Offline /");
        lcd.setCursor(0, 1);
        lcd.print("Tidak Ditemukan");
        delay(1500);
      }
    }
  }
  return url;
}

/**
 * @brief Mengambil URL server yang sudah di-resolve (menggunakan caching
 * non-blocking)
 */
String getResolvedServerUrl(bool forceRefresh = false) {
  if (cachedResolvedUrl == "" || forceRefresh ||
      (millis() - lastResolveTime > 60000)) {
    cachedResolvedUrl = resolveUrlMDNS(serverUrl, false);
    lastResolveTime = millis();
  }
  return cachedResolvedUrl;
}

// ==========================================
// 4. FUNGSI CONTROL HARDWARE & RFID MAINTENANCE
// ==========================================

/**
 * @brief Mengontrol status Relay Solenoid Pintu
 * @param on True untuk mengaktifkan relay (buka), False untuk mematikan (kunci)
 */
void setRelay(bool on) {
  if (on) {
    digitalWrite(RELAY_PIN, RELAY_ON);
  } else {
    digitalWrite(RELAY_PIN, RELAY_OFF);
  }
}

/**
 * @brief Pemeliharaan otomatis koneksi SPI & antena RFID MFRC522 untuk mencegah
 * hang
 */
void checkAndMaintainRFID() {
  static unsigned long lastCheck = 0;
  if (millis() - lastCheck < 2500)
    return;
  lastCheck = millis();

  byte version = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  // Jika chip MFRC522 terganggu / terputus (gagal membaca register SPI)
  if (version == 0x00 || version == 0xFF) {
    Serial.println("[RFID Maintenance] SPI/Chip MFRC522 terputus! "
                   "Menginisialisasi ulang...");
    SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
    rfid.PCD_Init();
    rfid.PCD_AntennaOn();
  } else {
    // Pastikan antena pemancar RFID (TX1 & TX2) selalu aktif (ON)
    byte txControl = rfid.PCD_ReadRegister(MFRC522::TxControlReg);
    if ((txControl & 0x03) != 0x03) {
      rfid.PCD_AntennaOn();
    }
  }
}

// ==========================================
// 5. FUNGSI HEARTBEAT PING (LIVE STATUS)
// ==========================================

/**
 * @brief Mengirimkan heartbeat ping berkala (tiap 3 detik) ke server untuk
 * status online real-time
 */
void sendHeartbeat() {
  static unsigned long lastHeartbeat = 0;
  if (millis() - lastHeartbeat < 3000 && lastHeartbeat != 0)
    return;
  lastHeartbeat = millis();

  if (WiFi.status() != WL_CONNECTED)
    return;

  String activeServerUrl = getResolvedServerUrl();
  String pingUrl = activeServerUrl;

  if (pingUrl.indexOf("/scan.php") != -1) {
    pingUrl.replace("/scan.php", "/ping.php");
  } else if (pingUrl.endsWith("/")) {
    pingUrl += "ping.php";
  } else {
    pingUrl += "/ping.php";
  }

  pingUrl += "?device_id=" + deviceID;

  WiFiClient client;
  HTTPClient http;
  http.setTimeout(1000); // Timeout 1000ms agar non-blocking & cepat
  if (http.begin(client, pingUrl)) {
    int code = http.GET();
    if (code > 0) {
      Serial.println("[Heartbeat] Ping terkirim ke server (" + String(code) +
                     ") -> " + pingUrl);
    }
    http.end();
  }
}

// ==========================================
// 6. FUNGSI TAMPILAN LCD & ANIMASI
// ==========================================

/**
 * @brief Menampilkan layar Standby utama LCD
 */
void lcdHome() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("    Silakan     ");
  lcd.setCursor(0, 1);
  lcd.print("Tempelkan Kartu ");
}

/**
 * @brief Menampilkan animasi titik berjalan saat kartu RFID terbaca
 */
void lcdLoading() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Membaca Kartu");

  for (int i = 0; i < 3; i++) {
    lcd.setCursor(0, 1);
    lcd.print("Mohon Tunggu");
    delay(250);

    lcd.setCursor(12, 1);
    lcd.print(".");
    delay(250);

    lcd.print(".");
    delay(250);

    lcd.print(".");
    delay(250);

    lcd.setCursor(12, 1);
    lcd.print("   ");
  }
}

/**
 * @brief Tampilan paging teks panjang pada baris LCD secara bergantian
 */
void showText(String text, byte row, int displayDelay = 1500) {
  if (text.length() <= 16) {
    lcd.setCursor(0, row);
    lcd.print(text);

    for (int i = text.length(); i < 16; i++)
      lcd.print(" ");

    delay(1000);
    return;
  }

  int len = text.length();
  int pos = 0;
  while (pos < len) {
    String chunk = text.substring(pos, pos + 16);
    lcd.setCursor(0, row);
    lcd.print(chunk);

    for (int i = chunk.length(); i < 16; i++)
      lcd.print(" ");

    delay(displayDelay);
    pos += 16;
  }
}

/**
 * @brief Menampilkan pesan Selamat Datang & informasi Pengguna saat akses
 * diterima
 */
void lcdUser(String nama, String nidn, String role, String room,
             String subject) {
  // 1. Tampilkan ucapan selamat datang & Nama
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Selamat Datang");
  showText(nama, 1);

  if (role == "dosen") {
    // 2. Tampilkan NIDN khusus Dosen
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("NIDN");
    showText(nidn, 1);

    // 3. Tampilkan Mata Kuliah khusus Dosen
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Mata Kuliah");
    showText(subject, 1);
  }

  // 4. Tampilkan informasi Ruangan
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Ruangan");
  showText(room, 1);

  // 5. Tampilkan indikator Pintu Terbuka
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Pintu Terbuka");
  lcd.setCursor(0, 1);
  lcd.print("Silakan Masuk");
}

/**
 * @brief Menampilkan pesan Akses Ditolak di LCD
 */
void lcdDenied(String message) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("AKSES DITOLAK");
  showText(message, 1);

  lcdHome();
}

// ==========================================
// 7. INISIALISASI (SETUP)
// ==========================================

void setup() {
  Serial.begin(115200);
  delay(1000);

  String defaultServerUrl = "http://acheron-fedora.local/tes/api/scan.php";

  // Membaca konfigurasi terdaftar dariPreferences (NVS)
  preferences.begin("config", false);
  serverUrl = preferences.getString("serverUrl", defaultServerUrl);

  if (serverUrl == "http://192.168.1.100/tes/api/test.php" ||
      serverUrl.indexOf("/tes/api/test.php") >= 0) {
    serverUrl = defaultServerUrl;
    preferences.putString("serverUrl", serverUrl);
  }

  deviceID = preferences.getString("deviceID", "ESP32-01");
  if (deviceID == "") {
    deviceID = "ESP32-01";
  }
  preferences.end();

  Serial.println("URL Server Terbaca : " + serverUrl);
  Serial.println("Device ID Terbaca  : " + deviceID);

  // Inisialisasi Bus SPI & Module RFID RC522
  SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
  rfid.PCD_Init();

  // Inisialisasi Bus I2C & Display LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();

  // Inisialisasi Pin Relay & Tombol BOOT
  digitalWrite(RELAY_PIN, RELAY_OFF);
  pinMode(RELAY_PIN, OUTPUT);
  pinMode(BOOT_BTN_PIN, INPUT_PULLUP);

  // Cek Firmware RFID RC522
  byte version = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  Serial.print("Firmware RC522 : 0x");
  Serial.println(version, HEX);

  if (version == 0x91 || version == 0x92) {
    Serial.println("RC522 TERDETEKSI (Original)");
  } else if (version == 0x12 || version == 0x90 || version == 0x88) {
    Serial.println("RC522 TERDETEKSI (Varian Clone/Compatible)");
  } else {
    Serial.println("RC522 STATUS : Check Wiring / Power");
  }

  // Koneksi ke Jaringan WiFi
  connectWiFi();

  // Auto-Discovery Server di Jaringan Lokal
  discoverServerAuto();

  // Tampilkan Informasi Koneksi WiFi di LCD
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected:");
  lcd.setCursor(0, 1);
  lcd.print(WiFi.SSID().substring(0, 16));
  delay(3000);

  // Aktifkan mDNS Responder (http://esp32-rfid.local)
  String hostName = "esp32-rfid";
  if (MDNS.begin(hostName.c_str())) {
    Serial.println("mDNS Active: http://" + hostName + ".local");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Web Config:");
    lcd.setCursor(0, 1);
    lcd.print(hostName + ".local");
    delay(3000);
  }

  // Tampilkan layar Standby awal
  lcdHome();
  Serial.println("\nSystem Ready. Tempelkan kartu RFID...");
}

// ==========================================
// 8. LOOP UTAMA
// ==========================================

void loop() {
  // Process background task WiFiManager
  wm.process();

  // Cek tombol BOOT untuk Mode Registrasi (Tekan) & Reset WiFi (Tahan 3 Detik)
  if (digitalRead(BOOT_BTN_PIN) == LOW) {
    delay(50);
    if (digitalRead(BOOT_BTN_PIN) == LOW) {
      unsigned long startPress = millis();
      bool longPress = false;

      while (digitalRead(BOOT_BTN_PIN) == LOW) {
        if (millis() - startPress > 3000) {
          longPress = true;
          break;
        }
        delay(10);
      }

      if (longPress) {
        // --- LOGIC RESET WIFI (Tahan > 3 detik) ---
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Reset WiFi...");
        lcd.setCursor(0, 1);
        lcd.print("Restarting...");
        Serial.println("[System] Resetting WiFi Manager settings...");
        wm.resetSettings();
        delay(1000);
        ESP.restart();
      } else {
        // --- LOGIC MODE REGISTRASI (Tekan Singkat) ---
        // Tunggu tombol dilepas agar tidak double trigger
        while (digitalRead(BOOT_BTN_PIN) == LOW) {
          delay(10);
        }

        isRegistrationMode = !isRegistrationMode;

        if (isRegistrationMode) {
          lcd.clear();
          lcd.setCursor(0, 0);
          lcd.print("Mode Registrasi");
          lcd.setCursor(0, 1);
          lcd.print("Tempel Kartu...");
          Serial.println("[System] Masuk Mode Registrasi");
        } else {
          lcdHome();
          Serial.println("[System] Keluar Mode Registrasi");
        }
      }
    }
  }

  // Jika WiFi terputus, lewati loop RFID agar tidak blocking
  if (WiFi.status() != WL_CONNECTED) {
    static unsigned long lastReconnectMsg = 0;
    if (millis() - lastReconnectMsg > 5000) {
      Serial.println("[WiFi] Terputus. Menunggu koneksi ulang...");
      lastReconnectMsg = millis();
    }
    return;
  }

  // 1. Kirim Heartbeat Ping ke Server (Non-blocking tiap 3s)
  sendHeartbeat();

  // 2. Jaga kestabilan hardware & Antena MFRC522
  checkAndMaintainRFID();

  // 3. Cek keberadaan Kartu RFID
  if (!rfid.PICC_IsNewCardPresent())
    return;

  // 4. Baca Serial Kartu RFID
  if (!rfid.PICC_ReadCardSerial())
    return;

  // Konversi Byte UID ke Format Hex String UpperCase
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10)
      uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  // Hentikan komunikasi crypto dengan kartu agar tidak mengganggu scan
  // berikutnya
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println("\n===============================");
  Serial.print("UID Kartu Terbaca : ");
  Serial.println(uid);

  // Tampilkan animasi loading
  lcdLoading();

  // Ambil URL Server aktif (cached)
  String resolvedUrl = getResolvedServerUrl();

  HTTPClient http;
  WiFiClient client;

  http.begin(client, resolvedUrl.c_str());
  http.addHeader("Content-Type", "application/json");

  // Konstruksi Request Body JSON
  String json = "{";
  json += "\"device_id\":\"" + deviceID + "\",";
  json += "\"uid\":\"" + uid + "\"";
  json += "}";

  Serial.println("Mengirim request scan ke server...");
  Serial.println("  URL  : " + resolvedUrl);
  Serial.println("  JSON : " + json);

  int httpCode = http.POST(json);
  Serial.println("HTTP Response Code : " + String(httpCode));

  // Tampilkan status HTTP Code di LCD
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("HTTP Status:");
  lcd.setCursor(0, 1);
  lcd.print("Code = " + String(httpCode));
  delay(1500);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("Server Response Payload :\n" + response);

    // --- CEK JIKA SEDANG MODE REGISTRASI ---
    if (isRegistrationMode) {
      Serial.println("\n===== KARTU DIBACA UNTUK REGISTRASI =====");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("UID Terbaca:");
      lcd.setCursor(0, 1);
      lcd.print(uid);

      // Beri waktu user untuk melihat UID
      delay(2000);

      // Kembalikan ke tampilan registrasi
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Mode Registrasi");
      lcd.setCursor(0, 1);
      lcd.print("Tempel Kartu...");

      http.end();
      rfid.PCD_Init();
      return; // Lewati logika parsing JSON dan Relay
    }

    DynamicJsonDocument doc(512);
    DeserializationError error = deserializeJson(doc, response);

    if (error) {
      Serial.println("JSON Parsing Error : " + String(error.c_str()));
      setRelay(false);
      lcdDenied("JSON Error");
      http.end();
      rfid.PCD_Init();
      delay(1000);
      return;
    }

    String access = doc["access"];
    String nama = doc["name"];
    String nidn = doc["nidn"];
    String role = doc["role"];
    String room = doc["room"];
    String subject = doc["subject"];
    String message = doc["message"];

    if (access == "ALLOW") {
      Serial.println("\n===== AKSES DITERIMA (ALLOW) =====");

      // 1. Aktifkan Relay Solenoid (Pintu Buka)
      setRelay(true);

      // Jeda peredam noise lonjakan listrik relay pada LCD
      delay(200);
      lcd.init();
      lcd.backlight();

      // 2. Tampilkan pesan selamat datang di LCD
      lcdUser(nama, nidn, role, room, subject);

      // 3. Tahan solenoid terbuka sesuai durasi SOLENOID_DELAY_MS (5s)
      delay(SOLENOID_DELAY_MS);

      // 4. Kunci kembali pintu (Relay OFF)
      setRelay(false);

      // Peredam noise pasca relay mati
      delay(500);
      lcd.init();
      lcd.backlight();
      rfid.PCD_Init();

      // 5. Kembali ke tampilan Standby
      lcdHome();
    } else {
      Serial.println("\n===== AKSES DITOLAK (DENY) =====");
      setRelay(false);

      if (message.length() == 0) {
        message = "Hubungi Admin";
      }
      lcdDenied(message);
    }
  } else {
    Serial.println("HTTP Error : " + String(httpCode));
    setRelay(false);
    lcdDenied("Koneksi Error");
  }

  http.end();

  // Inisialisasi ulang reader RFID untuk menjamin kesiapan scan berikutnya
  rfid.PCD_Init();
  delay(1000);
}