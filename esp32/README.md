# Smart Gate ESP32

C++/Arduino firmware for ESP32 using two MFRC522 readers. SPI SCK/MOSI/MISO/RST are shared; Entry SS is GPIO 5 and Exit SS is GPIO 16. MQTT is used for telemetry and REST/HTTPS remains authoritative for RFID authorization and gate opening.

Set `DEVICE_KEY` and `MQTT_HOST` before deployment.
