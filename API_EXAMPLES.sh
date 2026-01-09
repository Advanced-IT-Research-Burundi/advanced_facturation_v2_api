#!/bin/bash

# Advanced Facturation API - cURL Examples
# Base URL: http://localhost:8000/api

BASE_URL="http://localhost:8000/api"
TOKEN="" # Add your token here after login

# ==================== AUTHENTICATION ====================

# 1. Register a new user
echo "=== REGISTER NEW USER ==="
curl -X POST "$BASE_URL/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "role_id": 1,
    "company_id": 1
  }'

# 2. Login
echo -e "\n=== LOGIN ==="
curl -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'

# Note: Copy the token from login response and set it above

# ==================== COMPANIES ====================

# 3. List Companies
echo -e "\n=== LIST COMPANIES ==="
curl -X GET "$BASE_URL/companies" \
  -H "Authorization: Bearer $TOKEN"

# 4. Create Company
echo -e "\n=== CREATE COMPANY ==="
curl -X POST "$BASE_URL/companies" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tech Solutions",
    "tp_type": "SARL",
    "tp_name": "Tech Solutions Ltd",
    "tp_TIN": "123456789",
    "tp_trade_number": "TR001",
    "tp_phone_number": "+1234567890",
    "tp_address_province": "Bujumbura",
    "tp_address_commune": "Bujumbura",
    "tp_address_quartier": "Downtown",
    "tp_address_avenue": "Main Ave",
    "tp_address_rue": "Main St",
    "tp_address_number": "123",
    "tp_fiscal_center": "BJ001",
    "tp_activity_sector": "Technology",
    "tp_legal_form": "SARL",
    "vat_taxpayer": "Yes",
    "ct_taxpayer": "Yes",
    "tl_taxpayer": "Yes",
    "system_or_device_id": "DEV001",
    "default_currency": "USD"
  }'

# 5. Get Company
echo -e "\n=== GET COMPANY ==="
curl -X GET "$BASE_URL/companies/1" \
  -H "Authorization: Bearer $TOKEN"

# 6. Update Company
echo -e "\n=== UPDATE COMPANY ==="
curl -X PUT "$BASE_URL/companies/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tech Solutions Updated"
  }'

# 7. Delete Company (Soft Delete)
echo -e "\n=== DELETE COMPANY ==="
curl -X DELETE "$BASE_URL/companies/1" \
  -H "Authorization: Bearer $TOKEN"

# 8. Restore Company
echo -e "\n=== RESTORE COMPANY ==="
curl -X POST "$BASE_URL/companies/1/restore" \
  -H "Authorization: Bearer $TOKEN"

# ==================== ROLES ====================

# 9. List Roles
echo -e "\n=== LIST ROLES ==="
curl -X GET "$BASE_URL/roles" \
  -H "Authorization: Bearer $TOKEN"

# 10. Create Role
echo -e "\n=== CREATE ROLE ==="
curl -X POST "$BASE_URL/roles" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Manager",
    "description": "Manager role with elevated permissions"
  }'

# ==================== USERS ====================

# 11. List Users
echo -e "\n=== LIST USERS ==="
curl -X GET "$BASE_URL/users" \
  -H "Authorization: Bearer $TOKEN"

# 12. Create User
echo -e "\n=== CREATE USER ==="
curl -X POST "$BASE_URL/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "password123",
    "role_id": 2,
    "company_id": 1
  }'

# ==================== PRODUCTS ====================

# 13. List Products
echo -e "\n=== LIST PRODUCTS ==="
curl -X GET "$BASE_URL/products" \
  -H "Authorization: Bearer $TOKEN"

# 14. Create Product
echo -e "\n=== CREATE PRODUCT ==="
curl -X POST "$BASE_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "item_code": "PROD001",
    "item_designation": "Laptop",
    "item_measurement_unit": "piece",
    "barcode": "1234567890123",
    "vat_rate": 18.5,
    "company_id": 1
  }'

# ==================== WAREHOUSES ====================

# 15. List Warehouses
echo -e "\n=== LIST WAREHOUSES ==="
curl -X GET "$BASE_URL/warehouses" \
  -H "Authorization: Bearer $TOKEN"

# 16. Create Warehouse
echo -e "\n=== CREATE WAREHOUSE ==="
curl -X POST "$BASE_URL/warehouses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Main Warehouse",
    "location": "Downtown",
    "company_id": 1
  }'

# ==================== CUSTOMERS ====================

# 17. List Customers
echo -e "\n=== LIST CUSTOMERS ==="
curl -X GET "$BASE_URL/customers" \
  -H "Authorization: Bearer $TOKEN"

# 18. Create Customer
echo -e "\n=== CREATE CUSTOMER ==="
curl -X POST "$BASE_URL/customers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "ABC Corporation",
    "customer_TIN": "987654321",
    "customer_phone": "+9876543210",
    "customer_address": "456 Business St",
    "vat_customer_payer": "Yes",
    "company_id": 1
  }'

# ==================== INVOICES ====================

# 19. List Invoices
echo -e "\n=== LIST INVOICES ==="
curl -X GET "$BASE_URL/invoices" \
  -H "Authorization: Bearer $TOKEN"

# 20. Create Invoice
echo -e "\n=== CREATE INVOICE ==="
curl -X POST "$BASE_URL/invoices" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_number": "INV001",
    "invoice_date": "2026-01-09",
    "invoice_type": "Standard",
    "invoice_identifier": "ID001",
    "invoice_currency": "USD",
    "tp_type": "SARL",
    "tp_name": "Tech Solutions Ltd",
    "tp_TIN": "123456789",
    "tp_trade_number": "TR001",
    "tp_phone_number": "+1234567890",
    "tp_fiscal_center": "BJ001",
    "vat_taxpayer": "Yes",
    "ct_taxpayer": "Yes",
    "tl_taxpayer": "Yes",
    "customer_name": "ABC Corporation",
    "customer_TIN": "987654321",
    "customer_address": "456 Business St",
    "vat_customer_payer": "Yes",
    "invoice_amount_nvat": 1000.00,
    "invoice_vat_amount": 185.00,
    "invoice_total_amount": 1185.00,
    "obr_submission_status": "PENDING",
    "company_id": 1,
    "customer_id": 1
  }'

# ==================== INVOICE ITEMS ====================

# 21. List Invoice Items
echo -e "\n=== LIST INVOICE ITEMS ==="
curl -X GET "$BASE_URL/invoice-items" \
  -H "Authorization: Bearer $TOKEN"

# 22. Create Invoice Item
echo -e "\n=== CREATE INVOICE ITEM ==="
curl -X POST "$BASE_URL/invoice-items" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 1,
    "item_designation": "Laptop - Dell",
    "item_quantity": 2,
    "item_price": 500.00,
    "item_ct": 0,
    "item_tl": 0,
    "item_ott_tax": 0,
    "item_tsce_tax": 0,
    "item_price_nvat": 500.00,
    "vat": 92.50,
    "item_price_wvat": 592.50,
    "item_total_amount": 1185.00
  }'

# ==================== STOCK MOVEMENTS ====================

# 23. List Stock Movements
echo -e "\n=== LIST STOCK MOVEMENTS ==="
curl -X GET "$BASE_URL/stock-movements" \
  -H "Authorization: Bearer $TOKEN"

# 24. Create Stock Movement
echo -e "\n=== CREATE STOCK MOVEMENT ==="
curl -X POST "$BASE_URL/stock-movements" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "system_or_device_id": "DEV001",
    "item_code": "PROD001",
    "item_designation": "Laptop",
    "item_quantity": 2,
    "item_measurement_unit": "piece",
    "item_purchase_or_sale_price": 500.00,
    "item_purchase_or_sale_currency": "USD",
    "item_movement_type": "OUT",
    "item_movement_invoice_ref": "INV001",
    "item_movement_description": "Sale to ABC Corporation",
    "item_movement_date": "2026-01-09",
    "obr_submission_status": "PENDING",
    "company_id": 1,
    "product_id": 1,
    "warehouse_id": 1
  }'

# ==================== WAREHOUSE PRODUCTS ====================

# 25. List Warehouse Products
echo -e "\n=== LIST WAREHOUSE PRODUCTS ==="
curl -X GET "$BASE_URL/warehouse-products" \
  -H "Authorization: Bearer $TOKEN"

# 26. Create Warehouse Product
echo -e "\n=== CREATE WAREHOUSE PRODUCT ==="
curl -X POST "$BASE_URL/warehouse-products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "warehouse_id": 1,
    "quantity": 50,
    "unit_price": 500.00,
    "currency": "USD",
    "last_stock_movement_id": 1
  }'

# ==================== LOGOUT ====================

# 27. Logout
echo -e "\n=== LOGOUT ==="
curl -X POST "$BASE_URL/logout" \
  -H "Authorization: Bearer $TOKEN"

echo -e "\n\nAll examples completed!"
