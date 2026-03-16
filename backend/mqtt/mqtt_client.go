package mqtt

import (
	"fmt"

	mqtt "github.com/eclipse/paho.mqtt.golang"

	"parkir-dit/config"
	"parkir-dit/handler"
)

func StartMQTT(cfg config.Config) {
	opts := mqtt.NewClientOptions()
	opts.AddBroker(cfg.MQTTBroker)

	opts.SetClientID("golang-rfid-worker")

	client := mqtt.NewClient(opts)

	if token := client.Connect(); token.Wait() && token.Error() != nil {
		panic(token.Error())
	}

	fmt.Println("MQTT Connected")

	client.Subscribe(cfg.MQTTTopic, 0, func(client mqtt.Client, msg mqtt.Message) {
		handler.HandleMessage(cfg, client, msg)
	})
}
