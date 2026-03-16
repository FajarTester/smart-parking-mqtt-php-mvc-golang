package service

type Transaction struct {
	UID      int    `json:"uid"`
	Status   string `json:"status"` // "IN" atau "OUT"
	CheckIn  string `json:"check_in,omitempty"`
	CheckOut string `json:"check_out,omitempty"`
}
