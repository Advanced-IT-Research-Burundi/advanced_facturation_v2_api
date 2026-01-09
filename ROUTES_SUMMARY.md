# API Routes Summary

## List of All Available Routes

### Authentication Routes (No Auth Required)
- `POST /api/register` - Register new user
- `POST /api/login` - Login user

### User Routes (Auth Required)
- `GET /api/user` - Get current user
- `POST /api/logout` - Logout

---

## Resource Routes (All require auth:sanctum middleware)

### Companies
- `GET /api/companies` - List all companies (paginated)
- `POST /api/companies` - Create new company
- `GET /api/companies/{id}` - Get specific company
- `PUT /api/companies/{id}` - Update company
- `DELETE /api/companies/{id}` - Delete company (soft delete)
- `POST /api/companies/{id}/restore` - Restore deleted company

### Roles
- `GET /api/roles` - List all roles
- `POST /api/roles` - Create new role
- `GET /api/roles/{id}` - Get specific role
- `PUT /api/roles/{id}` - Update role
- `DELETE /api/roles/{id}` - Delete role
- `POST /api/roles/{id}/restore` - Restore deleted role

### Role Users (User Roles Assignment)
- `GET /api/role-users` - List all role assignments
- `POST /api/role-users` - Assign role to user
- `GET /api/role-users/{id}` - Get specific role assignment
- `DELETE /api/role-users/{id}` - Remove role from user
- `POST /api/role-users/{id}/restore` - Restore deleted assignment

### Users
- `GET /api/users` - List all users
- `POST /api/users` - Create new user
- `GET /api/users/{id}` - Get specific user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user
- `POST /api/users/{id}/restore` - Restore deleted user

### Products
- `GET /api/products` - List all products
- `POST /api/products` - Create new product
- `GET /api/products/{id}` - Get specific product
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product
- `POST /api/products/{id}/restore` - Restore deleted product

### Warehouses
- `GET /api/warehouses` - List all warehouses
- `POST /api/warehouses` - Create new warehouse
- `GET /api/warehouses/{id}` - Get specific warehouse
- `PUT /api/warehouses/{id}` - Update warehouse
- `DELETE /api/warehouses/{id}` - Delete warehouse
- `POST /api/warehouses/{id}/restore` - Restore deleted warehouse

### Customers
- `GET /api/customers` - List all customers
- `POST /api/customers` - Create new customer
- `GET /api/customers/{id}` - Get specific customer
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer
- `POST /api/customers/{id}/restore` - Restore deleted customer

### Invoices
- `GET /api/invoices` - List all invoices
- `POST /api/invoices` - Create new invoice
- `GET /api/invoices/{id}` - Get specific invoice
- `PUT /api/invoices/{id}` - Update invoice
- `DELETE /api/invoices/{id}` - Delete invoice
- `POST /api/invoices/{id}/restore` - Restore deleted invoice

### Invoice Items
- `GET /api/invoice-items` - List all invoice items
- `POST /api/invoice-items` - Create new invoice item
- `GET /api/invoice-items/{id}` - Get specific invoice item
- `PUT /api/invoice-items/{id}` - Update invoice item
- `DELETE /api/invoice-items/{id}` - Delete invoice item
- `POST /api/invoice-items/{id}/restore` - Restore deleted invoice item

### Stock Movements
- `GET /api/stock-movements` - List all stock movements
- `POST /api/stock-movements` - Create new stock movement
- `GET /api/stock-movements/{id}` - Get specific stock movement
- `PUT /api/stock-movements/{id}` - Update stock movement
- `DELETE /api/stock-movements/{id}` - Delete stock movement
- `POST /api/stock-movements/{id}/restore` - Restore deleted stock movement

### Warehouse Products
- `GET /api/warehouse-products` - List all warehouse products
- `POST /api/warehouse-products` - Create warehouse product
- `GET /api/warehouse-products/{id}` - Get specific warehouse product
- `PUT /api/warehouse-products/{id}` - Update warehouse product
- `DELETE /api/warehouse-products/{id}` - Delete warehouse product
- `POST /api/warehouse-products/{id}/restore` - Restore deleted warehouse product

---

## Total Routes Count
- **Authentication**: 2
- **User Management**: 2
- **Resources**: 12 resources × 6 operations = 72 routes
- **Total**: 76 API endpoints

---

## Controllers Created
1. `CompanyController` - Complete CRUD + restore
2. `RoleController` - Complete CRUD + restore
3. `RoleUserController` - CRUD + restore
4. `UserController` - Complete CRUD + restore
5. `ProductController` - Complete CRUD + restore
6. `WarehouseController` - Complete CRUD + restore
7. `CustomerController` - Complete CRUD + restore
8. `InvoiceController` - Complete CRUD + restore
9. `InvoiceItemController` - Complete CRUD + restore
10. `StockMovementController` - Complete CRUD + restore
11. `WarehouseProductController` - Complete CRUD + restore

---

## Features
✅ Full CRUD operations for all resources
✅ Soft deletes support with restore functionality
✅ Authentication with Sanctum tokens
✅ Request validation on all endpoints
✅ Relationship eager loading
✅ Pagination (15 items per page)
✅ User tracking (user_id for created_by)
✅ Proper HTTP status codes
✅ Standardized JSON response format
✅ Error handling
