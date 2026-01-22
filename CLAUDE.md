# CLAUDE.md


## Project Overview

AdvancedFacturation is a full-stack invoicing and inventory management system for Burundian businesses with OBR (Office Burundais des Recettes) tax integration.

- **Backend**: Laravel 12 REST API (`advanced_facturation_v2_api`)
- **Frontend**: Vue 3 + Vite SPA (`advanced_facturation_v2_ui-`)

## Development Commands

### Backend (Laravel API)

```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env && php artisan key:generate

# Run migrations
php artisan migrate

# Start development server (includes queue worker)
composer run dev

# Run server only
php artisan serve  # localhost:8000

# Run tests
composer run test

# Run single test
php artisan test --filter=TestClassName
php artisan test tests/Feature/ExampleTest.php
```

### Frontend (Vue.js)

```bash
# Install dependencies
npm install

# Start dev server
npm run dev  # localhost:5173

# Build for production
npm run build
```

## Architecture

### Backend Structure

```
app/
├── Http/Controllers/Api/   # 30+ REST controllers
├── Models/                 # Eloquent models (Invoice, Product, Warehouse, Customer, etc.)
├── Services/               # Business logic (OBR integration, etc.)
└── Helpers/                # Utility classes

routes/api.php              # All API route definitions
```

**Key patterns:**

- Controllers return JSON with `{ success: bool, data: ..., message?: string }`
- All routes use `auth:sanctum` middleware except `/login` and `/register`
- Soft deletes enabled on most models with `/restore` endpoints
- Company-scoped data (most queries filter by `company_id`)

### Frontend Structure

```
src/
├── views/                  # Page components organized by feature
│   ├── sales/              # Invoice management
│   ├── stocks/             # Warehouse/inventory
│   ├── products/           # Product catalog
│   ├── finance/            # Cash register, reminders, currencies
│   └── pharmaceutical/     # Pharmacy-specific features
├── components/layout/      # MainLayout, navigation
├── services/api.js         # Axios instance with auth interceptor
├── router/index.js         # Vue Router config
└── store/                  # Vuex state management
```

**Key patterns:**

- Composition API with `<script setup>`
- API calls via `@/services/api` (Axios with token auth)
- Bootstrap 5 + PrimeVue components
- Lucide icons

### API Base URL

Frontend connects to: `http://localhost:8000/api` (configurable via `VITE_API_BASE_URL`)

## Database

MySQL with these main entities:

- `companies` - Multi-tenant support
- `users` - Authentication, belongs to company
- `invoices` / `invoice_items` - Sales with OBR integration
- `products` / `warehouses` / `warehouse_products` - Inventory
- `customers` - Client management
- `payments` - Partial payment tracking
- `cash_registers` / `cash_movements` - Daily cash management

## OBR Integration

Tax authority integration in `app/Services/` and `app/Helpers/ApiConfiguration.php`. Invoices sync with OBR for fiscal compliance. See `OBR_API_DOC.md` for details.

## Testing

Backend uses PHPUnit with SQLite in-memory database:

- `tests/Feature/` - API endpoint tests
- `tests/Unit/` - Unit tests

## Key Files

- `routes/api.php` - All API endpoints
- `app/Models/Invoice.php` - Core invoice model with payment status tracking
- `src/router/index.js` - Frontend routing
- `src/services/api.js` - API client configuration
