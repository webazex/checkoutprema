# CheckoutPrema

CheckoutPrema is an e-commerce platform built with the **Yii2 Advanced Project Template**. It features a modern storefront, a public API, and deep integration with KeyCRM for product management and order fulfillment.

> **Note:** The active codebase is located in the `advanced/` directory. The `app/` directory contains a legacy version of the project and is scheduled for removal.

## Overview

The project is divided into several sub-applications:
- **Frontend (`advanced/frontend`)**: Public-facing site with catalog, cart, and checkout.
- **API (`advanced/api`)**: JSON API for external integrations and payment callbacks.
- **Console (`advanced/console`)**: CLI tools for synchronization with KeyCRM.
- **Common (`advanced/common`)**: Shared models, services, and integration logic.

## Requirements

- **PHP**: ^8.0
- **Extensions**: `ext-curl`, `ext-json`, `ext-mbstring`
- **Database**: MySQL 5.7+ / 8.0+
- **Package Manager**: [Composer](https://getcomposer.org/)

## Setup & Installation

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd checkoutprema
   ```

2. **Install dependencies**:
   ```bash
   cd advanced
   composer install
   ```

3. **Initialize the application**:
   Run the following command and select your environment (e.g., `0` for Development):
   ```bash
   php init
   ```

4. **Configure the Database**:
   Update `advanced/common/config/main-local.php` with your database credentials:
   ```php
   'db' => [
       'class' => \yii\db\Connection::class,
       'dsn' => 'mysql:host=localhost;dbname=YOUR_DB_NAME',
       'username' => 'YOUR_USERNAME',
       'password' => 'YOUR_PASSWORD',
       'charset' => 'utf8',
   ],
   ```

5. **Run Migrations**:
   ```bash
   php yii migrate
   ```

## Environment Variables & Configuration

Key configuration parameters should be defined in `advanced/common/config/params-local.php`:

- `keycrm.baseUrl`: The API URL for KeyCRM.
- `keycrm.token`: Your KeyCRM API token.
- `keycrm.timeout`: (Optional) API request timeout (default: 30s).

Example `params-local.php`:
```php
return [
    'keycrm.baseUrl' => 'https://api.keycrm.app/v1/',
    'keycrm.token' => 'your-secure-token',
];
```

## Console Scripts & Commands

The project includes several CLI commands for data synchronization:

- **Import Categories**: `php yii keycrm/import-categories`
- **Import Products**: `php yii keycrm/import-products`
- **Link Products to Categories**: `php yii keycrm/link-product-categories`
- **Cleanup Archived Products**: `php yii keycrm/cleanup-archived-products`

## Project Structure

```text
.
├── advanced/               # Active codebase
│   ├── api/                # JSON API application
│   ├── backend/            # Admin/Backend application
│   ├── common/             # Shared logic (Models, Services, DI)
│   ├── console/            # CLI commands and migrations
│   ├── frontend/           # Public storefront
│   └── tests/              # Project-wide tests
├── app/                    # [LEGACY] To be removed
└── PROJECT_DOCUMENTATION.md # Detailed technical documentation (RU)
```

## Testing

The project uses **Codeception** for testing.

- Run all tests:
  ```bash
  vendor/bin/codecept run
  ```
- Run specific suite (e.g., unit):
  ```bash
  vendor/bin/codecept run unit
  ```

## License

This project is licensed under the **BSD-3-Clause License**. See `advanced/LICENSE.md` for details.

---
*TODO: Add specific deployment instructions for production environment.*
*TODO: Document WayForPay callback configuration.*