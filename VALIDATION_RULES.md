# API Validation Rules

This document outlines all validation rules for each resource endpoint.

---

## Company

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `name` | required, string, max:255 | Company name |
| `tp_type` | required, string, max:255 | Taxpayer type |
| `tp_name` | required, string, max:255 | Taxpayer name |
| `tp_TIN` | required, string, unique, max:255 | Tax ID Number (unique) |
| `tp_trade_number` | nullable, string, max:255 | Trade number |
| `tp_postal_number` | nullable, string, max:255 | Postal number |
| `tp_phone_number` | nullable, string, max:255 | Phone number |
| `tp_address_province` | nullable, string, max:255 | Province |
| `tp_address_commune` | nullable, string, max:255 | Commune |
| `tp_address_quartier` | nullable, string, max:255 | Quartier |
| `tp_address_avenue` | nullable, string, max:255 | Avenue |
| `tp_address_rue` | nullable, string, max:255 | Street |
| `tp_address_number` | nullable, string, max:255 | Address number |
| `tp_fiscal_center` | nullable, string, max:255 | Fiscal center |
| `tp_activity_sector` | nullable, string, max:255 | Activity sector |
| `tp_legal_form` | nullable, string, max:255 | Legal form |
| `vat_taxpayer` | required, string, max:255 | VAT taxpayer status |
| `ct_taxpayer` | required, string, max:255 | CT taxpayer status |
| `tl_taxpayer` | required, string, max:255 | TL taxpayer status |
| `system_or_device_id` | required, string, max:255 | Device ID |
| `default_currency` | required, string, max:255 | Default currency |

---

## Role

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `name` | required, string, unique, max:255 | Role name (unique) |
| `description` | nullable, string | Role description |

---

## User

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `name` | nullable, string, max:255 | User name |
| `email` | required, email, unique, max:255 | Email address (unique) |
| `password` | required, string, min:6 | Password (min 6 chars) |
| `role_id` | required, exists:roles,id | Role ID (must exist) |
| `company_id` | required, exists:companies,id | Company ID (must exist) |

---

## Product

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `item_code` | required, string, unique, max:255 | Product code (unique) |
| `item_designation` | required, string, max:255 | Product name |
| `item_measurement_unit` | required, string, max:255 | Unit (kg, piece, etc) |
| `barcode` | nullable, string, max:255 | Barcode |
| `vat_rate` | required, numeric, min:0, max:100 | VAT rate (0-100) |
| `company_id` | required, exists:companies,id | Company ID (must exist) |

---

## Warehouse

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `name` | required, string, max:255 | Warehouse name |
| `location` | nullable, string, max:255 | Location |
| `company_id` | required, exists:companies,id | Company ID (must exist) |

---

## Customer

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `customer_name` | required, string, max:255 | Customer name |
| `customer_TIN` | nullable, string, unique, max:255 | Tax ID (unique if provided) |
| `customer_phone` | nullable, string, unique, max:255 | Phone (unique if provided) |
| `customer_address` | nullable, string, max:255 | Address |
| `vat_customer_payer` | required, string, max:255 | VAT payer status |
| `company_id` | required, exists:companies,id | Company ID (must exist) |

---

## Invoice

### Create Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `invoice_number` | required, string, max:255 | Invoice number |
| `invoice_date` | required, date | Invoice date |
| `invoice_type` | required, string, max:255 | Invoice type |
| `invoice_identifier` | required, string, max:255 | Invoice identifier |
| `invoice_currency` | required, string, max:3 | Currency code (e.g., USD) |
| `tp_type` | required, string, max:255 | Taxpayer type |
| `tp_name` | required, string, max:255 | Taxpayer name |
| `tp_TIN` | required, string, max:255 | Taxpayer ID |
| `tp_trade_number` | nullable, string, max:255 | Trade number |
| `tp_phone_number` | nullable, string, max:255 | Phone |
| `tp_fiscal_center` | nullable, string, max:255 | Fiscal center |
| `vat_taxpayer` | required, string, max:255 | VAT status |
| `ct_taxpayer` | required, string, max:255 | CT status |
| `tl_taxpayer` | required, string, max:255 | TL status |
| `customer_name` | required, string, max:255 | Customer name |
| `customer_TIN` | nullable, string, max:255 | Customer ID |
| `customer_address` | nullable, string, max:255 | Customer address |
| `vat_customer_payer` | required, string, max:255 | Customer VAT status |
| `invoice_amount_nvat` | required, numeric, min:0 | Amount without VAT |
| `invoice_vat_amount` | required, numeric, min:0 | VAT amount |
| `invoice_total_amount` | required, numeric, min:0 | Total amount |
| `invoice_registered_number` | nullable, string, max:255 | Registered number |
| `invoice_registered_date` | nullable, date | Registered date |
| `electronic_signature` | nullable, string | Signature |
| `obr_submission_status` | required, in:PENDING,SENT,ACCEPTED,REJECTED | Status |
| `obr_response_message` | nullable, string | OBR response |
| `company_id` | required, exists:companies,id | Company (must exist) |
| `customer_id` | required, exists:customers,id | Customer (must exist) |

### Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `invoice_number` | sometimes, required, string, max:255 | Invoice number |
| `invoice_date` | sometimes, required, date | Invoice date |
| `invoice_type` | sometimes, required, string, max:255 | Invoice type |
| `invoice_identifier` | sometimes, required, string, max:255 | Invoice identifier |
| `invoice_currency` | sometimes, required, string, max:3 | Currency code |
| `obr_submission_status` | sometimes, required, in:PENDING,SENT,ACCEPTED,REJECTED | Status |
| `obr_response_message` | nullable, string | OBR response |
| `invoice_registered_number` | nullable, string, max:255 | Registered number |
| `invoice_registered_date` | nullable, date | Registered date |
| `electronic_signature` | nullable, string | Signature |

---

## InvoiceItem

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `invoice_id` | required (create), exists:invoices,id | Invoice ID (must exist) |
| `item_designation` | required, string, max:255 | Item name |
| `item_quantity` | required, numeric, min:0.01 | Quantity |
| `item_price` | required, numeric, min:0 | Unit price |
| `item_ct` | nullable, numeric, min:0 | CT tax |
| `item_tl` | nullable, numeric, min:0 | TL tax |
| `item_ott_tax` | nullable, numeric, min:0 | OTT tax |
| `item_tsce_tax` | nullable, numeric, min:0 | TSCE tax |
| `item_price_nvat` | required, numeric, min:0 | Price without VAT |
| `vat` | required, numeric, min:0 | VAT amount |
| `item_price_wvat` | required, numeric, min:0 | Price with VAT |
| `item_total_amount` | required, numeric, min:0 | Total amount |

---

## StockMovement

### Create Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `system_or_device_id` | required, string, max:255 | Device ID |
| `item_code` | required, string, max:255 | Item code |
| `item_designation` | required, string, max:255 | Item name |
| `item_quantity` | required, numeric, min:0.01 | Quantity |
| `item_measurement_unit` | required, string, max:255 | Unit |
| `item_purchase_or_sale_price` | required, numeric, min:0 | Price |
| `item_purchase_or_sale_currency` | required, string, max:3 | Currency |
| `item_movement_type` | required, string, max:255 | Type (IN/OUT) |
| `item_movement_invoice_ref` | nullable, string, max:255 | Invoice reference |
| `item_movement_description` | nullable, string | Description |
| `item_movement_date` | required, date | Date |
| `obr_submission_status` | required, in:PENDING,SENT,ACCEPTED,REJECTED | Status |
| `obr_response_message` | nullable, string | Response |
| `obr_sent_at` | nullable, date | Sent date |
| `company_id` | required, exists:companies,id | Company (must exist) |
| `product_id` | required, exists:products,id | Product (must exist) |
| `warehouse_id` | required, exists:warehouses,id | Warehouse (must exist) |

### Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `item_quantity` | sometimes, required, numeric, min:0.01 | Quantity |
| `item_purchase_or_sale_price` | sometimes, required, numeric, min:0 | Price |
| `item_movement_invoice_ref` | nullable, string, max:255 | Invoice reference |
| `item_movement_description` | nullable, string | Description |
| `item_movement_date` | sometimes, required, date | Date |
| `obr_submission_status` | sometimes, required, in:PENDING,SENT,ACCEPTED,REJECTED | Status |
| `obr_response_message` | nullable, string | Response |
| `obr_sent_at` | nullable, date | Sent date |

---

## WarehouseProduct

### Create/Update Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `product_id` | required, exists:products,id | Product (must exist) |
| `warehouse_id` | required, exists:warehouses,id | Warehouse (must exist) |
| `quantity` | required, numeric, min:0 | Stock quantity |
| `unit_price` | required, numeric, min:0 | Unit price |
| `currency` | required, string, max:3 | Currency code |
| `last_stock_movement_id` | nullable, exists:stock_movements,id | Last movement (must exist if provided) |

**Note**: Unique constraint on (product_id, warehouse_id) - can't have duplicate

---

## RoleUser

### Create Validation Rules

| Field | Rules | Description |
|-------|-------|-------------|
| `role_id` | required, exists:roles,id | Role (must exist) |
| `user_id` | required, exists:users,id | User (must exist) |

**Note**: Unique constraint on (role_id, user_id) - can't assign same role twice

---

## Common Response Errors

### 422 Unprocessable Entity - Validation Failed
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 6 characters."]
  }
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 409 Conflict
```json
{
  "success": false,
  "message": "This email already exists.",
  "status": 409
}
```

---

## Validation Tips

- **`sometimes`** means the field is optional when updating but required when provided
- **`unique`** means the value can't be duplicated in the database
- **`nullable`** means the field can be `null`
- **`exists:table,column`** validates that the value exists in another table
- **Dates** should be in `YYYY-MM-DD` format
- **Numbers** can be integers or decimals
- **Strings** have maximum length limits

---

**Last Updated**: January 9, 2026
