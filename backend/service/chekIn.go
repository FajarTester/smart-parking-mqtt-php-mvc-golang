package service

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
)

func CheckIn(baseURL string, key string, uid string, client mqtt.Client) error {
	apiBase := baseURL
	if apiBase == "" {
		return fmt.Errorf("baseURL kosong")
	}

	cardID, err := strconv.ParseInt(uid, 10, 64)
	if err != nil {
		return fmt.Errorf("UID bukan angka valid: %v", err)
	}

	httpClient := &http.Client{Timeout: 10 * time.Second}

	checkURL := fmt.Sprintf("%s/rest/v1/parkir_tb_transaksi?card_id=eq.%d&status=eq.IN", apiBase, cardID)
	reqCheck, err := http.NewRequest("GET", checkURL, nil)
	if err != nil {
		return fmt.Errorf("gagal membuat request GET: %v", err)
	}
	reqCheck.Header.Set("apikey", key)
	reqCheck.Header.Set("Authorization", "Bearer "+key)
	reqCheck.Header.Set("Accept", "application/json")

	respCheck, err := httpClient.Do(reqCheck)
	if err != nil {
		return fmt.Errorf("gagal melakukan GET: %v", err)
	}
	defer respCheck.Body.Close()

	bodyCheck, _ := io.ReadAll(respCheck.Body)
	if respCheck.StatusCode != http.StatusOK {
		return fmt.Errorf("GET gagal, status %d: %s", respCheck.StatusCode, string(bodyCheck))
	}

	var existingRecords []map[string]interface{}
	if err := json.Unmarshal(bodyCheck, &existingRecords); err != nil {
		return fmt.Errorf("gagal decode response GET: %v - body: %s", err, string(bodyCheck))
	}

	if len(existingRecords) > 0 {
		fmt.Println("User sudah parkir, kirim pesan ke LCD")
		client.Publish("parking/fajar/lcd", 0, false, "SUDAH PARKIR")
		return nil
	}

	data := map[string]any{
		"card_id":      cardID,
		"checkin_time": time.Now().UTC().Format(time.RFC3339), // Postgres timestamptz OK
		"status":       "IN",
	}

	jsonData, err := json.Marshal(data)
	if err != nil {
		return fmt.Errorf("gagal marshal JSON: %v", err)
	}

	insertURL := fmt.Sprintf("%s/rest/v1/parkir_tb_transaksi", apiBase)
	reqInsert, err := http.NewRequest("POST", insertURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("gagal membuat request POST: %v", err)
	}
	reqInsert.Header.Set("apikey", key)
	reqInsert.Header.Set("Authorization", "Bearer "+key)
	reqInsert.Header.Set("Content-Type", "application/json")
	reqInsert.Header.Set("Accept", "application/json")
	reqInsert.Header.Set("Prefer", "return=representation")

	respInsert, err := httpClient.Do(reqInsert)
	if err != nil {
		return fmt.Errorf("gagal melakukan POST: %v", err)
	}
	defer respInsert.Body.Close()

	bodyInsert, _ := io.ReadAll(respInsert.Body)
	if respInsert.StatusCode != http.StatusCreated && respInsert.StatusCode != http.StatusOK {
		return fmt.Errorf("POST gagal, status %d: %s", respInsert.StatusCode, string(bodyInsert))
	}

	fmt.Printf("POST Status: %d\n", respInsert.StatusCode)
	fmt.Printf("POST Body: %s\n", string(bodyInsert))

	var inserted []map[string]interface{}
	if err := json.Unmarshal(bodyInsert, &inserted); err == nil && len(inserted) > 0 {
		fmt.Printf("Insert succeeded, id: %v\n", inserted[0]["id"])
	}

	client.Publish("parking/fajar/lcd", 0, false, "CHECKIN BERHASIL")
	return nil
}
