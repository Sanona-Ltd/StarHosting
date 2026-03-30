# StarHosting

<div align="center">

![StarHosting Banner](https://placehold.co/900x300/1e293b/6366f1?text=StarHosting+Control+Panel)

**A modern, self-hosted web hosting control panel built with PHP 8.3 + Symfony 7.1**

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.1-000000?style=flat-square&logo=symfony)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

</div>

---

## ⚡ One-Line Install

```bash
curl -fsSL https://raw.githubusercontent.com/sanona-ltd/starhosting/main/install.sh -o /tmp/starhosting-install.sh && sudo bash /tmp/starhosting-install.sh
```

> Requires Ubuntu 22.04/24.04 or Debian 11/12. Run as root.

---

## ✨ Features

- 🌐 **Virtual host management** — Create and delete Nginx/Apache vhosts with one click
- 📧 **Mail management** — Add domains, create IMAP/SMTP mailboxes via Postfix + Dovecot
- 📊 **Real-time server stats** — CPU, RAM, and disk usage on the dashboard
- 🔒 **Secure panel** — HTTPS on port 8443, Symfony Security with bcrypt, CSRF protection
- 🔑 **REST API** — JSON API with token authentication for programmatic access
- 💾 **Zero-config database** — SQLite via Doctrine ORM, no database server needed for the panel itself

---

## 🛠 Tech Stack

| Layer          | Technology                        |
|----------------|-----------------------------------|
| Language       | PHP 8.3                           |
| Framework      | Symfony 7.1                       |
| ORM            | Doctrine ORM 3 + SQLite           |
| Templating     | Twig + Tailwind CSS (CDN)         |
| UI             | Alpine.js, Inter font             |
| Auth           | Symfony Security, bcrypt          |
| Shell ops      | Symfony Process component         |
| Installer      | Bash (Ubuntu 22/24, Debian 11/12) |

---

## 🚀 Manual Installation

### Prerequisites

- PHP 8.3 with extensions: `sqlite3`, `mbstring`, `xml`, `curl`, `zip`, `intl`
- Composer 2
- Nginx or Apache2

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/sanona-ltd/starhosting.git /var/www/starhosting
cd /var/www/starhosting

# 2. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.example .env
# Edit .env and set APP_SECRET, PANEL_WEBSERVER, etc.

# 4. Run database migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Create your admin user
php bin/console app:setup

# 6. Configure your web server to point to the /public directory
```

---

## 🖥 Console Commands

| Command                          | Description                          |
|----------------------------------|--------------------------------------|
| `php bin/console app:setup`       | Create the initial admin user        |
| `php bin/console app:site:create` | Create a site from the CLI           |
| `php bin/console app:mail:create` | Create a mail account from the CLI   |

---

## 🔌 REST API

The panel exposes a JSON API at `/api/v1`. Authenticate using the `X-API-Token` header with a base64-encoded `email:password` token.

### Endpoints

| Method | Path                     | Description              |
|--------|--------------------------|--------------------------|
| GET    | `/api/v1/sites`          | List all sites           |
| POST   | `/api/v1/sites`          | Create a new site        |
| GET    | `/api/v1/mail/accounts`  | List all mail accounts   |
| POST   | `/api/v1/mail/accounts`  | Create a mail account    |

**Example:**
```bash
TOKEN=$(echo -n "admin@example.com:password" | base64)
curl -H "X-API-Token: $TOKEN" https://localhost:8443/api/v1/sites
```

---

## 🗺 Roadmap

- [x] Virtual host management (Nginx + Apache)
- [x] Mail domain & mailbox management
- [x] Dashboard with real-time server stats
- [x] REST API with token auth
- [x] Bash installer (Ubuntu/Debian)
- [ ] Let's Encrypt SSL automation
- [ ] FTP account management
- [ ] MySQL/MariaDB database management per site
- [ ] PHP version switching per site
- [ ] Multi-user / reseller support
- [ ] Backup & restore
- [ ] Cron job manager
- [ ] DNS zone editor
- [ ] Two-factor authentication (2FA)
- [ ] OpenLiteSpeed support

---

## 🤝 Contributing

Contributions are welcome! Please open an issue first to discuss what you would like to change.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add my feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a pull request

---

## 📄 License

Copyright © 2024 [Sanona Ltd](https://sanona.ltd). Released under the [MIT License](LICENSE).