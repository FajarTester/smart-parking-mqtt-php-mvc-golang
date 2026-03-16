package handler

import (
	"encoding/json"
	"fmt"
	"log"
	"strings"
	"time"

	"parkir-dit/config"
	"parkir-dit/service"

	mqtt "github.com/eclipse/paho.mqtt.golang"
)

func HandleMessage(cfg config.Config, client mqtt.Client, msg mqtt.Message) {
	topic := strings.TrimSpace(msg.Topic())
	payload := string(msg.Payload())

	if strings.Contains(topic, "/servo") {
		return
	}

	fmt.Printf("Diterima: [%s] | Payload: %s\n", topic, payload)

	switch topic {
	case "parking/fajar/entry/rfid":
		fmt.Println("LOG: Proses Kendaraan MASUK")

		var pkt map[string]string
		if err := json.Unmarshal(msg.Payload(), &pkt); err != nil {
			log.Printf("invalid payload: %v", err)
			return
		}

		uidStr, ok := pkt["uid"]
		if !ok {
			log.Println("payload tidak berisi uid")
			return
		}

		go func() {
			if err := service.CheckIn(cfg.SupabaseURL, cfg.SupabaseKey, uidStr, client); err != nil {
				log.Printf("CheckIn error: %v", err)
			}
		}()

		client.Publish("parking/fajar/entry/servo", 0, false, "OPEN")
		time.Sleep(1 * time.Second)
		client.Publish("parking/fajar/entry/servo", 0, false, "CLOSE")

	case "parking/fajar/exit/rfid":
		fmt.Println("LOG: Proses Kendaraan KELUAR")

		var pkt map[string]string
		if err := json.Unmarshal(msg.Payload(), &pkt); err != nil {
			log.Printf("invalid payload: %v", err)
			return
		}

		uidStr, ok := pkt["uid"]
		if !ok {
			log.Println("payload tidak berisi uid")
			return
		}

		go func() {
			if err := service.CheckOut(cfg.SupabaseURL, cfg.SupabaseKey, uidStr, client); err != nil {
				log.Printf("CheckOut error: %v", err)
			}
		}()
		client.Publish("parking/fajar/exit/servo", 0, false, "OPEN")
		time.Sleep(1 * time.Second)
		client.Publish("parking/fajar/exit/servo", 0, false, "CLOSE")

	case "parking/fajar/entry/servo", "parking/fajar/exit/servo":
		return

	case "parking/fajar/lcd":
		return

	default:
		fmt.Printf("PERINGATAN: Topic '%s' tidak dikenal oleh switch\n", topic)
	}
}
