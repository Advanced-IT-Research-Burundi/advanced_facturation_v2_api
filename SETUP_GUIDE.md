# Advanced Facturation V2 API - Complete Setup Guide

## 🚀 Quick Start

### 1. Installation & Setup

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate:fresh

# Start development server
php artisan serve
```

### 2. Database Seeding (Optional)

Create seeds to populate test data:
```bash
php artisan make:seeder CompanySeeder
php artisan make:seeder RoleSeeder
php artisan make:seeder UserSeeder
php artisan db:seed
```

### 3. Test the API

Use Postman, Insomnia, or cURL to test endpoints.

**Example Login:**
```bash
curl -X POST "http://localhost:8000/api/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

---

## 📚 API Documentation

### Authentication
All protected endpoints require a Sanctum token:
```
Authorization: Bearer {token}
```

### Response Format
**Success Response:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...}
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {...}
}
```

---

## 🛣️ Available Routes

### Public Routes
- `POST /api/register` - Register new user
- `POST /api/login` - Login and get token

### Protected Routes (require authentication)

#### Companies (12 endpoints)
- `GET /api/companies` - List companies
- `POST /api/companies` - Create company
- `GET /api/companies/{id}` - Get company
- `PUT /api/companies/{id}` - Update company
- `DELETE /api/companies/{id}` - Delete company
- `POST /api/companies/{id}/restore` - Restore company

#### Roles (6 endpoints)
- `GET /api/roles` - List roles
- `POST /api/roles` - Create role
- `GET /api/roles/{id}` - Get role
- `PUT /api/roles/{id}` - Update role
- `DELETE /api/roles/{id}` - Delete role
- `POST /api/roles/{id}/restore` - Restore role

#### Role Users (6 endpoints)
- `GET /api/role-users` - List assignments
- `POST /api/role-users` - Assign role to user
- `GET /api/role-users/{id}` - Get assignment
- `DELETE /api/role-users/{id}` - Remove role
- `POST /api/role-users/{id}/restore` - Restore assignment

#### Users (6 endpoints)
- `GET /api/users` - List users
- `POST /api/users` - Create user
- `GET /api/users/{id}` - Get user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user
- `POST /api/users/{id}/restore` - Restore user

#### Products (6 endpoints)
- `GET /api/products` - List products
- `POST /api/products` - Create product
- `GET /api/products/{id}` - Get product
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product
- `POST /api/products/{id}/restore` - Restore product

#### Warehouses (6 endpoints)
- `GET /api/warehouses` - List warehouses
- `POST /api/warehouses` - Create warehouse
- `GET /api/warehouses/{id}` - Get warehouse
- `PUT /api/warehouses/{id}` - Update warehouse
- `DELETE /api/warehouses/{id}` - Delete warehouse
- `POST /api/warehouses/{id}/restore` - Restore warehouse

#### Customers (6 endpoints)
- `GET /api/customers` - List customers
- `POST /api/customers` - Create customer
- `GET /api/customers/{id}` - Get customer
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer
- `POST /api/customers/{id}/restore` - Restore customer

#### Invoices (6 endpoints)
- `GET /api/invoices` - List invoices
- `POST /api/invoices` - Create invoice
- `GET /api/invoices/{id}` - Get invoice
- `PUT /api/invoices/{id}` - Update invoice
- `DELETE /api/invoices/{id}` - Delete invoice
- `POST /api/invoices/{id}/restore` - Restore invoice

#### Invoice Items (6 endpoints)
- `GET /api/invoice-items` - List items
- `POST /api/invoice-items` - Create item
- `GET /api/invoice-items/{id}` - Get item
- `PUT /api/invoice-items/{id}` - Update item
- `DELETE /api/invoice-items/{id}` - Delete item
- `POST /api/invoice-items/{id}/restore` - Restore item

#### Stock Movements (6 endpoints)
- `GET /api/stock-movements` - List movements
- `POST /api/stock-movements` - Create movement
- `GET /api/stock-movements/{id}` - Get movement
- `PUT /api/stock-movements/{id}` - Update movement
- `DELETE /api/stock-movements/{id}` - Delete movement
- `POST /api/stock-movements/{id}/restore` - Restore movement

#### Warehouse Products (6 endpoints)
- `GET /api/warehouse-products` - List items
- `POST /api/warehouse-products` - Create item
- `GET /api/warehouse-products/{id}` - Get item
- `PUT /api/warehouse-products/{id}` - Update item
- `DELETE /api/warehouse-products/{id}` - Delete item
- `POST /api/warehouse-products/{id}/restore` - Restore item

---

## 📋 Complete Controller Methods

Each resource has the following methods:

### index()
Returns paginated list of resources (15 per page)
```
GET /api/{resource}
```

### store()
Creates a new resource with validation
```
POST /api/{resource}
```

### show()
Returns a single resource with relationships
```
GET /api/{resource}/{id}
```

### update()
Updates a resource with partial data validation
```
PUT /api/{resource}/{id}
```

### destroy()
Soft deletes a resource
```
DELETE /api/{resource}/{id}
```

### restore()
Restores a soft-deleted resource
```
POST /api/{resource}/{id}/restore
```

---

## 🔐 Features

✅ **Complete CRUD Operations** - All resources support Create, Read, Update, Delete
✅ **Soft Deletes** - Data isn't permanently deleted, can be restored
✅ **Sanctum Authentication** - Secure token-based API auth
✅ **Request Validation** - All inputs validated with proper error messages
✅ **Relationship Loading** - Eager loading of related data
✅ **Pagination** - Results paginated (15 items per page)
✅ **User Tracking** - `user_id` field tracks who created/modified each record
✅ **Status Codes** - Proper HTTP status codes (200, 201, 204, 400, 401, 404, 422, 409, 500)
✅ **Error Handling** - Comprehensive error responses with validation details
✅ **JSON Responses** - Standardized JSON format for all responses

---

## 🧪 Testing with Postman

### Import Collection
1. Open Postman
2. Click "Import"
3. Create new requests for each endpoint
4. Set Authorization header with Bearer token from login response

### Example Request

**POST /api/companies**
```
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "name": "Tech Solutions",
  "tp_type": "SARL",
  "tp_name": "Tech Solutions Ltd",
  "tp_TIN": "123456789",
  ...
}
```

---

## 🐛 Troubleshooting

### 401 Unauthorized
- Ensure you're sending the token in Authorization header
- Format: `Bearer {token}`
- Token may have expired, login again

### 422 Unprocessable Entity
- Check validation errors in response
- Ensure all required fields are present
- Check field types and formats

### 404 Not Found
- Resource ID doesn't exist
- Check the ID in the URL

### 409 Conflict
- Duplicate unique constraint (e.g., duplicate email)
- Or trying to assign same role twice to a user

---

## 📝 Models & Relationships

### Company
- Has many: invoices, warehouses, users, products
- Soft deletes enabled

### Role
- Has many: users
- Soft deletes enabled

### User
- Belongs to: role, company
- Has many: invoices, stock movements
- Soft deletes enabled

### Product
- Belongs to: company
- Has many: stock movements, warehouse products
- Soft deletes enabled

### Warehouse
- Belongs to: company
- Has many: stock movements, warehouse products
- Soft deletes enabled

### Customer
- Belongs to: company
- Has many: invoices
- Soft deletes enabled

### Invoice
- Belongs to: company, customer, user (created_by)
- Has many: invoice items, stock movements
- Soft deletes enabled

### InvoiceItem
- Belongs to: invoice
- Soft deletes enabled

### StockMovement
- Belongs to: company, product, warehouse, user (created_by)
- Soft deletes enabled

### WarehouseProduct
- Belongs to: product, warehouse, stock movement
- Soft deletes enabled

---

## 🚀 Production Deployment

### Environment Setup
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=your-host
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

### Deployment Steps
```bash
# Install dependencies
composer install --optimize-autoloader --no-dev

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Clear and optimize cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# Set proper permissions
chmod -R 775 storage bootstrap/cache
```

---

## 📞 Support

For issues or questions, refer to:
- `API_DOCUMENTATION.md` - Full endpoint documentation
- `ROUTES_SUMMARY.md` - Complete routes list
- `API_EXAMPLES.sh` - cURL examples for testing

---

## 📄 License

This project is proprietary software developed for Advanced IT Research Burundi.

---

**Version**: 2.0  
**Last Updated**: January 9, 2026
