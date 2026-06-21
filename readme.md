## Sakuragi Tailoring Sales and Inventory Management

Run the local PHP server from the project root:

```bash
php -S localhost:8000
```

Current structure:

```text
app/
  Controllers/   Legacy PHP request handlers and controller scripts
  DataAccess/    Legacy DAO classes
  Middleware/    Canonical auth and role guards
  Models/        Legacy model/value objects
  Support/       Shared helper functions
  Views/Shared/  Canonical shared layout partials and sidebars
auth/            Login, register, logout pages
config/          Bootstrap, DB, constants, migrations, SQL
dashboards/      Admin, employee, and customer screens
public/          Landing page, assets, images, uploads
src/             PSR-4 namespaced application code
tests/           Test files
```

Notes:

- `app/` now holds the old root-level backend files that were previously split across `controller/`, `dao/`, and `models/`.
- `app/Middleware`, `app/Support`, and `app/Views/Shared` are now the canonical home for shared PHP code.
- `config/db_connect.php` is the canonical PDO bootstrap; `config/database.php` now exists only as a compatibility wrapper.
- `src/` remains the proper PSR-4 code area for newer namespaced classes.
- `public/` still contains the landing page and static assets used by the browser.
