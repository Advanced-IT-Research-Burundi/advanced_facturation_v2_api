# API Documentation - Advanced Facturation V2

## Base URL
```
http://localhost:8000/api
```

## Authentication
All endpoints (except `/register` and `/login`) require authentication using Sanctum tokens.
Include the token in the header:
```
Authorization: Bearer {token}
```

---

## Public Endpoints

### Register
- **Endpoint**: `POST /register`
- **Description**: Create a new user account
- **Body**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "role_id": 1,
  "company_id": 1
}
```

### Login
- **Endpoint**: `POST /login`
- **Description**: Authenticate user and get API token
- **Body**:
```json
{
  "email": "john@example.com",
  "password": "password"
}
```
- **Response**:
```json
{
  "success": true,
  "token": "...",
  "user": {...}
}
```

---

## Protected Endpoints

### Logout
- **Endpoint**: `POST /logout`
- **Method**: POST
- **Description**: Logout and revoke token

---

## Companies

### List Companies
- **Endpoint**: `GET /companies`
- **Method**: GET
- **Query Params**: `page=1` (pagination)

### Create Company
- **Endpoint**: `POST /companies`
- **Method**: POST
- **Body**:
```json
{
  "name": "Company Name",
  "tp_type": "Type",
  "tp_name": "TP Name",
  "tp_TIN": "123456789",
  "tp_trade_number": "TR123",
  "tp_postal_number": "1234",
  "tp_phone_number": "+1234567890",
  "tp_address_province": "Province",
  "tp_address_commune": "Commune",
  "tp_address_quartier": "Quartier",
  "tp_address_avenue": "Avenue",
  "tp_address_rue": "Rue",
  "tp_address_number": "123",
  "tp_fiscal_center": "Center",
  "tp_activity_sector": "Sector",
  "tp_legal_form": "Form",
  "vat_taxpayer": "Yes",
  "ct_taxpayer": "Yes",
  "tl_taxpayer": "Yes",
  "system_or_device_id": "DEV001",
  "default_currency": "USD"
}
```

### Get Company
- **Endpoint**: `GET /companies/{id}`
- **Method**: GET

### Update Company
- **Endpoint**: `PUT /companies/{id}`
- **Method**: PUT
- **Body**: (same as create, all fields optional with `sometimes`)

### Delete Company
- **Endpoint**: `DELETE /companies/{id}`
- **Method**: DELETE
- **Note**: Soft delete

### Restore Company
- **Endpoint**: `POST /companies/{id}/restore`
- **Method**: POST

---

## Roles

### List Roles
- **Endpoint**: `GET /roles`
- **Method**: GET

### Create Role
- **Endpoint**: `POST /roles`
- **Method**: POST
- **Body**:
```json
{
  "name": "Admin",
  "description": "Administrator role"
}
```

### Get Role
- **Endpoint**: `GET /roles/{id}`
- **Method**: GET

### Update Role
- **Endpoint**: `PUT /roles/{id}`
- **Method**: PUT

### Delete Role
- **Endpoint**: `DELETE /roles/{id}`
- **Method**: DELETE

### Restore Role
- **Endpoint**: `POST /roles/{id}/restore`
- **Method**: POST

---

## Users

### List Users
- **Endpoint**: `GET /users`
- **Method**: GET

### Create User
- **Endpoint**: `POST /users`
- **Method**: POST
- **Body**:
```json
{
  "name": "User Name",
  "email": "user@example.com",
  "password": "password",
  "role_id": 1,
  "company_id": 1
}
```

### Get User
- **Endpoint**: `GET /users/{id}`
- **Method**: GET

### Update User
- **Endpoint**: `PUT /users/{id}`
- **Method**: PUT

### Delete User
- **Endpoint**: `DELETE /users/{id}`
- **Method**: DELETE

### Restore User
- **Endpoint**: `POST /users/{id}/restore`
- **Method**: POST

---

## Role Users (User Roles Assignment)

### List Role Users
- **Endpoint**: `GET /role-users`
- **Method**: GET

### Assign Role to User
- **Endpoint**: `POST /role-users`
- **Method**: POST
- **Body**:
```json
{
  "role_id": 1,
  "user_id": 1
}
```

### Get Role User Assignment
- **Endpoint**: `GET /role-users/{id}`
- **Method**: GET

### Remove Role from User
- **Endpoint**: `DELETE /role-users/{id}`
- **Method**: DELETE

### Restore Role User Assignment
- **Endpoint**: `POST /role-users/{id}/restore`
- **Method**: POST

---

## Products

### List Products
- **Endpoint**: `GET /products`
- **Method**: GET

### Create Product
- **Endpoint**: `POST /products`
- **Method**: POST
- **Body**:
```json
{
  "item_code": "P001",
  "item_designation": "Product Name",
  "item_measurement_unit": "kg",
  "barcode": "123456789",
  "vat_rate": 18.5,
  "company_id": 1
}
```

### Get Product
- **Endpoint**: `GET /products/{id}`
- **Method**: GET

### Update Product
- **Endpoint**: `PUT /products/{id}`
- **Method**: PUT

### Delete Product
- **Endpoint**: `DELETE /products/{id}`
- **Method**: DELETE

### Restore Product
- **Endpoint**: `POST /products/{id}/restore`
- **Method**: POST

---

## Warehouses

### List Warehouses
- **Endpoint**: `GET /warehouses`
- **Method**: GET

### Create Warehouse
- **Endpoint**: `POST /warehouses`
- **Method**: POST
- **Body**:
```json
{
  "name": "Main Warehouse",
  "location": "Downtown",
  "company_id": 1
}
```

### Get Warehouse
- **Endpoint**: `GET /warehouses/{id}`
- **Method**: GET

### Update Warehouse
- **Endpoint**: `PUT /warehouses/{id}`
- **Method**: PUT

### Delete Warehouse
- **Endpoint**: `DELETE /warehouses/{id}`
- **Method**: DELETE

### Restore Warehouse
- **Endpoint**: `POST /warehouses/{id}/restore`
- **Method**: POST

---

## Customers

### List Customers
- **Endpoint**: `GET /customers`
- **Method**: GET

### Create Customer
- **Endpoint**: `POST /customers`
- **Method**: POST
- **Body**:
```json
{
  "customer_name": "Customer Name",
  "customer_TIN": "987654321",
  "customer_phone": "+1234567890",
  "customer_address": "123 Street",
  "vat_customer_payer": "Yes",
  "company_id": 1
}
```

### Get Customer
- **Endpoint**: `GET /customers/{id}`
- **Method**: GET

### Update Customer
- **Endpoint**: `PUT /customers/{id}`
- **Method**: PUT

### Delete Customer
- **Endpoint**: `DELETE /customers/{id}`
- **Method**: DELETE

### Restore Customer
- **Endpoint**: `POST /customers/{id}/restore`
- **Method**: POST

---

## Invoices

### List Invoices
- **Endpoint**: `GET /invoices`
- **Method**: GET

### Create Invoice
- **Endpoint**: `POST /invoices`
- **Method**: POST
- **Body**:
```json
{
  "invoice_number": "INV001",
  "invoice_date": "2026-01-09",
  "invoice_type": "Type",
  "invoice_identifier": "ID001",
  "invoice_currency": "USD",
  "tp_type": "Type",
  "tp_name": "TP Name",
  "tp_TIN": "123456789",
  "tp_trade_number": "TR123",
  "tp_phone_number": "+1234567890",
  "tp_fiscal_center": "Center",
  "vat_taxpayer": "Yes",
  "ct_taxpayer": "Yes",
  "tl_taxpayer": "Yes",
  "customer_name": "Customer",
  "customer_TIN": "987654321",
  "customer_address": "123 Street",
  "vat_customer_payer": "Yes",
  "invoice_amount_nvat": 1000.00,
  "invoice_vat_amount": 180.00,
  "invoice_total_amount": 1180.00,
  "obr_submission_status": "PENDING",
  "company_id": 1,
  "customer_id": 1
}
```

### Get Invoice
- **Endpoint**: `GET /invoices/{id}`
- **Method**: GET

### Update Invoice
- **Endpoint**: `PUT /invoices/{id}`
- **Method**: PUT

### Delete Invoice
- **Endpoint**: `DELETE /invoices/{id}`
- **Method**: DELETE

### Restore Invoice
- **Endpoint**: `POST /invoices/{id}/restore`
- **Method**: POST

---

## Invoice Items

### List Invoice Items
- **Endpoint**: `GET /invoice-items`
- **Method**: GET

### Create Invoice Item
- **Endpoint**: `POST /invoice-items`
- **Method**: POST
- **Body**:
```json
{
  "invoice_id": 1,
  "item_designation": "Item Name",
  "item_quantity": 5,
  "item_price": 100.00,
  "item_ct": 0,
  "item_tl": 0,
  "item_ott_tax": 0,
  "item_tsce_tax": 0,
  "item_price_nvat": 100.00,
  "vat": 18.00,
  "item_price_wvat": 118.00,
  "item_total_amount": 590.00
}
```

### Get Invoice Item
- **Endpoint**: `GET /invoice-items/{id}`
- **Method**: GET

### Update Invoice Item
- **Endpoint**: `PUT /invoice-items/{id}`
- **Method**: PUT

### Delete Invoice Item
- **Endpoint**: `DELETE /invoice-items/{id}`
- **Method**: DELETE

### Restore Invoice Item
- **Endpoint**: `POST /invoice-items/{id}/restore`
- **Method**: POST

---

## Stock Movements

### List Stock Movements
- **Endpoint**: `GET /stock-movements`
- **Method**: GET

### Create Stock Movement
- **Endpoint**: `POST /stock-movements`
- **Method**: POST
- **Body**:
```json
{
  "system_or_device_id": "DEV001",
  "item_code": "P001",
  "item_designation": "Product",
  "item_quantity": 10,
  "item_measurement_unit": "kg",
  "item_purchase_or_sale_price": 100.00,
  "item_purchase_or_sale_currency": "USD",
  "item_movement_type": "IN",
  "item_movement_invoice_ref": "INV001",
  "item_movement_description": "Stock entry",
  "item_movement_date": "2026-01-09",
  "obr_submission_status": "PENDING",
  "company_id": 1,
  "product_id": 1,
  "warehouse_id": 1
}
```

### Get Stock Movement
- **Endpoint**: `GET /stock-movements/{id}`
- **Method**: GET

### Update Stock Movement
- **Endpoint**: `PUT /stock-movements/{id}`
- **Method**: PUT

### Delete Stock Movement
- **Endpoint**: `DELETE /stock-movements/{id}`
- **Method**: DELETE

### Restore Stock Movement
- **Endpoint**: `POST /stock-movements/{id}/restore`
- **Method**: POST

---

## Warehouse Products

### List Warehouse Products
- **Endpoint**: `GET /warehouse-products`
- **Method**: GET

### Create Warehouse Product
- **Endpoint**: `POST /warehouse-products`
- **Method**: POST
- **Body**:
```json
{
  "product_id": 1,
  "warehouse_id": 1,
  "quantity": 100,
  "unit_price": 50.00,
  "currency": "USD",
  "last_stock_movement_id": 1
}
```

### Get Warehouse Product
- **Endpoint**: `GET /warehouse-products/{id}`
- **Method**: GET

### Update Warehouse Product
- **Endpoint**: `PUT /warehouse-products/{id}`
- **Method**: PUT

### Delete Warehouse Product
- **Endpoint**: `DELETE /warehouse-products/{id}`
- **Method**: DELETE

### Restore Warehouse Product
- **Endpoint**: `POST /warehouse-products/{id}/restore`
- **Method**: POST

---

## Response Format

All successful responses follow this format:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...}
}
```

Error responses:
```json
{
  "success": false,
  "message": "Error message",
  "errors": {...}
}
```

---

## HTTP Status Codes
- `200 OK` - Successful GET, PUT
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Missing or invalid token
- `404 Not Found` - Resource not found
- `409 Conflict` - Duplicate/conflicting data
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error
