# Purple Helpdesk — Ticketing & Support System

A formal, purple-themed internal ticketing system built in plain PHP + MySQL,
with role-based access (Admin / Agent), ticket follow-ups, email notifications
via PHPMailer, and built-in analytics dashboards.

## Features

- **Authentication**: login, forgot/reset password (emailed secure link), forced
  password change for new accounts.
- **Roles**: Admin (full access) and Agent (sees only assigned tickets).
- **Tickets**: add/edit/delete, branch, category, priority + SLA due date,
  file attachments, search & multi-filter, CSV export, pagination.
- **Follow-ups**: threaded conversation per ticket, optional internal-only
  notes (admin only), optional "next follow-up" scheduled date, attachments.
- **Status workflow**: Open → In Progress → On Hold → Resolved → Closed, with
  a full audit timeline per ticket.
- **Notifications**: in-app bell dropdown + full notifications page, and
  automatic HTML emails (PHPMailer) on ticket creation, assignment, status
  change, and new follow-ups.
- **Employees**: admin can add/edit/deactivate agents & admins; new employees
  get an emailed temporary password.
- **Dashboard**: stat cards, status doughnut chart, 14-day trend line,
  progress bars, tickets-by-branch bar chart, recent tickets table.
- **Analytics**: SLA compliance, average resolution time, agent performance
  leaderboard, category/priority breakdowns, 6-month logged-vs-resolved trend.
- **Settings**: manage branches, categories, priority/SLA levels, site name,
  and a full activity/audit log — all admin only.
- **Security**: PDO prepared statements everywhere, CSRF tokens on every
  form/AJAX call, password hashing (bcrypt), session-based auth, `.htaccess`
  locks on `/config`, `/includes`, and `/uploads`.

## Requirements

- PHP 7.4+ (tested against PHP 8.x)
- MySQL 5.7+ / MariaDB 10.3+
- Composer (recommended) for installing PHPMailer
- Apache or Nginx with PHP-FPM (any standard LAMP/WAMP/XAMPP stack works)

## Setup

1. **Copy the files** to your web server, e.g. `htdocs/ticketing-system` (XAMPP)
   or `/var/www/ticketing-system`.

2. **Create the database.** Import the schema:

   ```bash
   mysql -u root -p < database/schema.sql
   ```

   This creates the `ticketing_system` database, all tables, and seed data
   (4 sample branches, 6 categories, 4 priority levels, and one admin user).

3. **Install PHPMailer** via Composer:

   ```bash
   cd ticketing-system
   composer install
   ```

   If you can't use Composer on your host, download PHPMailer manually from
   https://github.com/PHPMailer/PHPMailer and place its `src/` folder at
   `libs/PHPMailer/src/` — `includes/mailer.php` will automatically fall back
   to that path if `vendor/autoload.php` doesn't exist.

4. **Configure the app** — edit `config/config.php`:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — your database credentials
   - `BASE_URL` — the full URL where the app is hosted (no trailing slash)
   - `APP_SECRET` — replace with a long random string

5. **Configure email** — edit `config/mail_config.php` with your SMTP
   provider's details (Gmail SMTP, Outlook365, SendGrid, Mailgun, or your
   company mail server). Set `'enabled' => false` to disable email sending
   while developing locally.

6. **Set folder permissions** so uploads work:

   ```bash
   chmod -R 755 uploads/ assets/images/avatars/
   ```

7. **Log in** at `login.php` with the default administrator account:

   - Username: `admin`
   - Password: `Admin@123`

   You will be prompted to change this password immediately — please do so
   before putting the system into real use.

## Adding your own images

- Drop your logo into `assets/images/logo/` and update the commented `<img>`
  tag in `includes/sidebar.php` (search for `TODO`).
- Drop a favicon into the same folder and uncomment the `<link rel="icon">`
  tag in `includes/header.php`.
- See `assets/images/README.txt` for the full list of placeholder spots.

## Folder structure

```
ticketing-system/
├── ajax/                 AJAX endpoints (follow-ups, status, assignment, notifications)
├── assets/
│   ├── css/style.css     Purple theme (all styling lives here)
│   ├── js/main.js        Sidebar, dropdowns, notification polling
│   └── images/           Your logo/avatars go here
├── config/
│   ├── config.php        Database + site configuration
│   └── mail_config.php   SMTP / PHPMailer configuration
├── database/schema.sql   Full schema + seed data
├── includes/             Shared header/sidebar/footer, auth, mailer, helpers
├── uploads/tickets/      Ticket & follow-up attachments
├── dashboard.php, tickets.php, view_ticket.php, add_ticket.php, ...
└── README.md
```

## Notes on the two-tier role model

- **Admins** see and manage everything: all tickets, all employees, branches,
  categories, priorities, and analytics.
- **Agents** only see tickets assigned to them (or that they personally
  logged) on their dashboard, ticket list, and analytics is admin-only. They
  can change status and add follow-ups on their own tickets.

If you'd rather have a simpler single-admin setup, or want to add a third
"client/branch self-service" login tier, the schema (`users.role` is an ENUM)
and `includes/auth.php` are the two places to extend.
