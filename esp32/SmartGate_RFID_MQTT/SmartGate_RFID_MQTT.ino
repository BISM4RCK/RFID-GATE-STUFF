/* Smart Gate - ESP32 + dual MFRC522 + MQTT + HTTPS. C++/Arduino. */
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>

const char* WIFI_SSID = "TP_link1";
const char* WIFI_PASSWORD = "Benq2877";
const char* BASE_URL = "https://gate.kunehobatumbakal.site";
const char* DEVICE_ID = "180503";
const char* DEVICE_KEY = "2jGpAbQGBVW9qJU89UQjDxNAjNMtj2q-JsuvL9dE8Ig";
const char* MQTT_HOST = "CHANGE_ME_MQTT_BROKER";
const uint16_t MQTT_PORT = 1883;

constexpr uint8_t SCK_PIN=18, MOSI_PIN=23, MISO_PIN=19, RST_PIN=22;
constexpr uint8_t ENTRY_SS=5, ENTRY_GREEN=25, ENTRY_RED=26;
constexpr uint8_t EXIT_SS=16, EXIT_GREEN=32, EXIT_RED=33;
constexpr uint8_t SYS_GREEN=13, SYS_RED=14, RELAY=27;
constexpr uint32_t GATE_MS=2500, LED_MS=1500, HEARTBEAT_MS=5000, COMMAND_POLL_MS=1000;
const char* FIRMWARE_VERSION = "61.0";
constexpr uint16_t COOLDOWN=650;
constexpr uint32_t HTTP_TIMEOUT_MS=5000, MQTT_RETRY_MS=5000;

MFRC522 entryReader(ENTRY_SS, RST_PIN);
MFRC522 exitReader(EXIT_SS, RST_PIN);
WiFiClientSecure tls;
WiFiClient mqttNet;
PubSubClient mqtt(mqttNet);

unsigned long gateUntil=0, entryLedUntil=0, exitLedUntil=0;
unsigned long lastEntryScan=0, lastExitScan=0, lastHeartbeat=0, lastCommandPoll=0, lastMqttAttempt=0;

String uid(MFRC522& reader) {
    String value;
    for (byte i=0; i<reader.uid.size; i++) {
        if (reader.uid.uidByte[i] < 16) value += '0';
        value += String(reader.uid.uidByte[i], HEX);
    }
    value.toUpperCase();
    return value;
}

void openGate() {
    digitalWrite(RELAY, HIGH);
    gateUntil = millis() + GATE_MS;
}

void setLeds(bool entryGate, bool approved) {
    uint8_t green = entryGate ? ENTRY_GREEN : EXIT_GREEN;
    uint8_t red = entryGate ? ENTRY_RED : EXIT_RED;
    digitalWrite(green, approved ? HIGH : LOW);
    digitalWrite(red, approved ? LOW : HIGH);
    if (entryGate) entryLedUntil = millis() + LED_MS;
    else exitLedUntil = millis() + LED_MS;
}

void ensureWiFi() {
    if (WiFi.status() == WL_CONNECTED) return;
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

bool mqttEnabled() {
    return MQTT_HOST && String(MQTT_HOST).length() > 0 && String(MQTT_HOST) != "CHANGE_ME_MQTT_BROKER";
}

void mqttEvent(const String& reader, const String& value) {
    if (!mqttEnabled() || !mqtt.connected()) return;
    String topic = String("smartgate/") + DEVICE_ID + "/rfid/" + reader;
    String payload = String("{\"uid\":\"") + value + "\",\"reader\":\"" + reader + "\"}";
    mqtt.publish(topic.c_str(), payload.c_str());
}

bool authorize(const String& reader, const String& value) {
    tls.setInsecure();
    HTTPClient http;
    String url = String(BASE_URL) + "/api/esp32/rfid/scan";
    if (!http.begin(tls, url)) return false;
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-SmartGate-Device", DEVICE_KEY);
    String body = "device_id=" + String(DEVICE_ID) + "&rfid_uid=" + value + "&reader=" + reader;
    int code = http.POST(body);
    String response = http.getString();
    http.end();
    return code >= 200 && code < 300 && response.indexOf("\"gate_opened\":true") >= 0;
}

void completeCommand(const String& id) {
    tls.setInsecure();
    HTTPClient http;
    String url = String(BASE_URL) + "/api/esp32/gate/commands/" + id + "/complete";
    if (!http.begin(tls, url)) return;
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("X-SmartGate-Device", DEVICE_KEY);
    http.POST("");
    http.end();
}

void pollCommands() {
    tls.setInsecure();
    HTTPClient http;
    String url = String(BASE_URL) + "/api/esp32/gate/commands";
    if (!http.begin(tls, url)) return;
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("X-SmartGate-Device", DEVICE_KEY);
    const int code = http.GET();
    if (code >= 200 && code < 300) {
        const String body = http.getString();
        DynamicJsonDocument doc(8192);
        const DeserializationError error = deserializeJson(doc, body);
        if (!error) {
            JsonArray commands = doc["commands"].as<JsonArray>();
            for (JsonObject command : commands) {
                const String id = String((unsigned long)(command["id"] | 0));
                const String commandName = command["command"] | "";
                if (!id.length()) continue;

                if (commandName == "restart_device") {
                    completeCommand(id);
                    delay(100);
                    ESP.restart();
                }

                if (commandName == "open") {
                    const char* gate = command["payload"]["gate"] | "";
                    const bool entryGate = String(gate) == "entry";
                    const bool exitGate = String(gate) == "exit";
                    if (!entryGate && !exitGate) {
                        completeCommand(id);
                        continue;
                    }
                    openGate();
                    setLeds(entryGate, true);
                    completeCommand(id);
                }
            }
        }
    }
    http.end();
}

void heartbeat() {
    tls.setInsecure();
    HTTPClient http;
    String url = String(BASE_URL) + "/api/esp32/heartbeat";
    if (!http.begin(tls, url)) return;
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-SmartGate-Device", DEVICE_KEY);
    String ip = WiFi.localIP().toString();
    String mqttStatus = mqtt.connected() ? "online" : "offline";
    String body = String("device_id=") + DEVICE_ID + "&firmware_version=" + FIRMWARE_VERSION + "&ip_address=" + ip + "&mqtt_status=" + mqttStatus + "&wifi_rssi=" + String(WiFi.RSSI()) + "&free_heap=" + String(ESP.getFreeHeap()) + "&uptime_seconds=" + String(millis() / 1000UL) + "&wifi_status=" + (WiFi.status() == WL_CONNECTED ? "online" : "offline");
    http.POST(body);
    http.end();
}

void scanCard(const char* readerName, MFRC522& reader, bool entryGate, unsigned long& lastScan) {
    if (millis() - lastScan < COOLDOWN) return;
    if (!reader.PICC_IsNewCardPresent() || !reader.PICC_ReadCardSerial()) return;
    lastScan = millis();

    String value = uid(reader);
    mqttEvent(readerName, value);
    bool approved = authorize(readerName, value);
    setLeds(entryGate, approved);
    if (approved) openGate();

    reader.PICC_HaltA();
    reader.PCD_StopCrypto1();
}

void setup() {
    pinMode(ENTRY_GREEN, OUTPUT);
    pinMode(ENTRY_RED, OUTPUT);
    pinMode(EXIT_GREEN, OUTPUT);
    pinMode(EXIT_RED, OUTPUT);
    pinMode(SYS_GREEN, OUTPUT);
    pinMode(SYS_RED, OUTPUT);
    pinMode(RELAY, OUTPUT);
    digitalWrite(RELAY, LOW);

    SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN);
    entryReader.PCD_Init();
    exitReader.PCD_Init();
    entryReader.PCD_SetAntennaGain(MFRC522::RxGain_max);
    exitReader.PCD_SetAntennaGain(MFRC522::RxGain_max);

    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.persistent(false);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    tls.setInsecure();
    if (mqttEnabled()) mqtt.setServer(MQTT_HOST, MQTT_PORT);
}

void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        digitalWrite(SYS_GREEN, LOW);
        digitalWrite(SYS_RED, HIGH);
        if (millis() - lastHeartbeat >= 5000) {
            lastHeartbeat = millis();
            ensureWiFi();
        }
        delay(50);
        return;
    }

    digitalWrite(SYS_GREEN, HIGH);
    digitalWrite(SYS_RED, LOW);

    if (mqttEnabled()) {
        if (!mqtt.connected() && millis() - lastMqttAttempt >= MQTT_RETRY_MS) {
            lastMqttAttempt = millis();
            mqtt.connect((String("smartgate-") + DEVICE_ID).c_str());
        }
        mqtt.loop();
    }

    scanCard("entry", entryReader, true, lastEntryScan);
    scanCard("exit", exitReader, false, lastExitScan);

    if (millis() - lastHeartbeat >= HEARTBEAT_MS) {
        lastHeartbeat = millis();
        heartbeat();
    }

    if (millis() - lastCommandPoll >= COMMAND_POLL_MS) {
        lastCommandPoll = millis();
        pollCommands();
    }

    if (gateUntil && millis() >= gateUntil) {
        digitalWrite(RELAY, LOW);
        gateUntil = 0;
    }

    if (entryLedUntil && millis() >= entryLedUntil) {
        digitalWrite(ENTRY_GREEN, LOW);
        digitalWrite(ENTRY_RED, LOW);
        entryLedUntil = 0;
    }

    if (exitLedUntil && millis() >= exitLedUntil) {
        digitalWrite(EXIT_GREEN, LOW);
        digitalWrite(EXIT_RED, LOW);
        exitLedUntil = 0;
    }

    delay(5);
}
