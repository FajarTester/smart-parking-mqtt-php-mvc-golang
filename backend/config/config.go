package config

import (
	"log"
	"os"

	"github.com/joho/godotenv" // Tambahkan import ini
)

type Config struct {
	SupabaseKey string
	SupabaseURL string
	MQTTBroker  string
	MQTTTopic   string
}

func LoadConfig() Config {
	err := godotenv.Load()
	if err != nil {
		log.Println("Peringatan: File .env tidak ditemukan, menggunakan env system")
	}

	return Config{
		SupabaseKey: os.Getenv("SUPABASE_KEY"),
		SupabaseURL: os.Getenv("SUPABASE_URL"),
		MQTTBroker:  os.Getenv("MQTT_BROKER"),
		MQTTTopic:   os.Getenv("MQTT_TOPIC"),
	}
}
