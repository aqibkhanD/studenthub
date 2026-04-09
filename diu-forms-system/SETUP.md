# DIU Student Services — Setup Guide

## Requirements
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js 18+ (for frontend)
- Redis (optional, for queues)

---

## Local Setup

```bash
# 1. Create a new Laravel project and copy these files in
composer create-project laravel/laravel diu-forms
cd diu-forms

# 2. Install Sanctum (API auth)
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 3. Configure .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=diu_forms
DB_USERNAME=root
DB_PASSWORD=your_password

# SSL Wireless SMS gateway
SSL_WIRELESS_TOKEN=your_api_token_here
SSL_WIRELESS_SID=your_sid_here

# 4. Run migrations + seed data
php artisan migrate
php artisan db:seed

# 5. Register the role middleware in bootstrap/app.php
# Add: ->withMiddleware(function (Middleware $middleware) {
#          $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class]);
#      })

# 6. Start the local server
php artisan serve

# 7. Start the scheduler (development)
php artisan schedule:work
```

---

## Production Deployment (VPS — Ubuntu 22.04)

```bash
# Install stack
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl mysql-server

# Clone your repo
git clone https://github.com/your-org/diu-forms.git /var/www/diu-forms
cd /var/www/diu-forms
composer install --optimize-autoloader --no-dev
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Set permissions
sudo chown -R www-data:www-data /var/www/diu-forms/storage
sudo chmod -R 775 /var/www/diu-forms/storage

# Nginx config — /etc/nginx/sites-available/diu-forms
# server {
#     listen 80;
#     server_name forms.diu.edu.bd;
#     root /var/www/diu-forms/public;
#     index index.php;
#     location / { try_files $uri $uri/ /index.php?$query_string; }
#     location ~ \.php$ { fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; include fastcgi_params; }
# }

# Add cron for scheduler
# * * * * * cd /var/www/diu-forms && php artisan schedule:run >> /dev/null 2>&1

# SSL certificate (free)
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d forms.diu.edu.bd
```

---

## Key API Endpoints

### Authentication
| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/auth/login` | Login (student or admin) |
| POST | `/api/auth/register` | Student self-registration |
| POST | `/api/auth/logout` | Logout / revoke token |
| GET  | `/api/auth/me` | Current user profile |

### Student
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/student/form-types` | List available forms |
| GET  | `/api/student/form-types/{slug}` | Form detail + fields |
| POST | `/api/student/submissions` | Submit a new form |
| GET  | `/api/student/submissions` | My submissions |
| GET  | `/api/student/submissions/{ref}` | Submission detail + history |
| PUT  | `/api/student/submissions/{ref}` | Resubmit after return |
| DELETE | `/api/student/submissions/{ref}` | Cancel a draft |
| POST | `/api/student/submissions/{ref}/documents` | Attach a file |
| GET  | `/api/student/submissions/{ref}/comments` | View comments |

### Admin Queue
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/admin/dashboard` | Dashboard stats |
| GET  | `/api/admin/submissions` | Queue (filterable) |
| POST | `/api/admin/submissions/{ref}/approve` | Approve |
| POST | `/api/admin/submissions/{ref}/reject` | Reject |
| POST | `/api/admin/submissions/{ref}/return` | Return for correction |
| POST | `/api/admin/submissions/{ref}/escalate` | Escalate |
| POST | `/api/admin/submissions/{ref}/assign` | Assign to admin |
| POST | `/api/admin/submissions/{ref}/generate-document` | Trigger PDF generation |
| POST | `/api/admin/submissions/bulk` | Bulk status change |

### Super Admin — Settings
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/admin/settings/branding` | Read portal settings |
| PUT  | `/api/admin/settings/branding` | Update portal settings + feature flags |
| POST | `/api/admin/settings/branding/logo` | Upload logo (multipart) |
| DELETE | `/api/admin/settings/branding/logo` | Remove logo |

### Super Admin — Form Types
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/admin/form-types` | List all form types |
| POST | `/api/admin/form-types` | Create form type (with fields) |
| GET  | `/api/admin/form-types/{id}` | Get form type + fields |
| PUT  | `/api/admin/form-types/{id}` | Update form type + fields |
| DELETE | `/api/admin/form-types/{id}` | Delete (or deactivate if has submissions) |
| POST | `/api/admin/form-types/{id}/toggle` | Toggle active state |
| GET  | `/api/admin/form-types/{id}/fields` | List fields |
| POST | `/api/admin/form-types/{id}/fields` | Add a field |
| PUT  | `/api/admin/form-types/{id}/fields/{fieldId}` | Update a field |
| DELETE | `/api/admin/form-types/{id}/fields/{fieldId}` | Remove a field |
| POST | `/api/admin/form-types/{id}/fields/reorder` | Reorder fields |

### Super Admin — Departments
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/admin/departments` | List all departments |
| POST | `/api/admin/departments` | Create department |
| GET  | `/api/admin/departments/{id}` | Get department + signatory |
| PUT  | `/api/admin/departments/{id}` | Update department + signatory + SLA rules |
| DELETE | `/api/admin/departments/{id}` | Delete (or deactivate) |
| POST | `/api/admin/departments/{id}/toggle` | Toggle active state |
| POST | `/api/admin/departments/{id}/signatory-logo` | Upload signature image |
| DELETE | `/api/admin/departments/{id}/signatory-logo` | Remove signature image |

### Super Admin — Users
| Method | URL | Description |
|--------|-----|-------------|
| GET  | `/api/admin/users` | List admin/super_admin users |
| POST | `/api/admin/users` | Create admin user |
| GET  | `/api/admin/users/me` | Own profile |
| PUT  | `/api/admin/users/me` | Update own profile / password |
| GET  | `/api/admin/users/{id}` | Get user |
| PUT  | `/api/admin/users/{id}` | Update user |
| DELETE | `/api/admin/users/{id}` | Delete (or deactivate) |
| POST | `/api/admin/users/{id}/toggle` | Toggle active state |
| POST | `/api/admin/users/{id}/role` | Change role |
| POST | `/api/admin/users/{id}/reset-password` | Set new password |

### Certificate Verification (Public)
| Method | URL | Description |
|--------|-----|-------------|
| GET | `/verify/{ref}` | Public certificate verification (JSON) |
| GET | `/submissions/{ref}/download` | Download certificate PDF (signed URL) |
| POST | `/api/student/submissions/{ref}/download-link` | Generate fresh download link |

All protected routes require: `Authorization: Bearer {token}`

---

## Services Config (config/services.php)

```php
'ssl_wireless' => [
    'token' => env('SSL_WIRELESS_TOKEN'),
    'sid'   => env('SSL_WIRELESS_SID'),
],
```
