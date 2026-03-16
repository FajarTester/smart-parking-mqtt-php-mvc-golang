package main

import (
	"fmt"
	"parkir-dit/config"
	"parkir-dit/mqtt"
)

func main() {
	cfg := config.LoadConfig()

	fmt.Println("Starting MQTT WORKER")

	mqtt.StartMQTT(cfg)

	select {}
}
