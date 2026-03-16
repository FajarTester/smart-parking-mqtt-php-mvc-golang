package service

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
)

func CheckOut(url string, key string, uid string, mqttClient mqtt.Client) error {
	httpClient := &http.Client{}

	cardID, err := strconv.ParseInt(uid, 10, 64)
	if err != nil {
		return fmt.Errorf("UID tidak valid: %v", err)
	}

	getURL := fmt.Sprintf("%s/rest/v1/parkir_tb_transaksi?card_id=eq.%d&status=eq.IN&select=id,checkin_time", url, cardID)
	reqGet, _ := http.NewRequest("GET", getURL, nil)
	reqGet.Header.Set("apikey", key)
	reqGet.Header.Set("Authorization", "Bearer "+key)

	respGet, err := httpClient.Do(reqGet)
	if err != nil {
		return err
	}
	defer respGet.Body.Close()

	var records []map[string]interface{}
	json.NewDecoder(respGet.Body).Decode(&records)

	if len(records) == 0 {
		mqttClient.Publish("parking/fajar/lcd", 0, false, "BELUM CHECK-IN")
		return fmt.Errorf("data tidak ditemukan")
	}

	id := records[0]["id"]
	checkinStr := records[0]["checkin_time"].(string)
	checkInTime, _ := time.Parse(time.RFC3339, checkinStr)
	checkOutTime := time.Now().UTC()

	durationMinutes := int(checkOutTime.Sub(checkInTime).Minutes())
	if durationMinutes < 1 {
		durationMinutes = 1
	}

	hours := (durationMinutes / 60) + 1
	fee := hours * 3000

	updateData := map[string]any{
		"checkout_time": checkOutTime.Format(time.RFC3339),
		"status":        "OUT",
		"duration":      durationMinutes,
		"fee":           fee,
	}

	jsonData, _ := json.Marshal(updateData)
	patchURL := fmt.Sprintf("%s/rest/v1/parkir_tb_transaksi?id=eq.%v", url, id)

	reqPatch, _ := http.NewRequest("PATCH", patchURL, bytes.NewBuffer(jsonData))
	reqPatch.Header.Set("apikey", key)
	reqPatch.Header.Set("Authorization", "Bearer "+key)
	reqPatch.Header.Set("Content-Type", "application/json")

	_, err = httpClient.Do(reqPatch)
	if err != nil {
		return err
	}

	pesanLCD := fmt.Sprintf("OUT: RP %d", fee)
	mqttClient.Publish("parking/fajar/lcd", 0, false, pesanLCD)

	fmt.Printf("User %d Keluar. Biaya: %d\n", cardID, fee)
	return nil
}
