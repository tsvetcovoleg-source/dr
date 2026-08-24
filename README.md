# Decision Rules Demo

A portable, database-driven credit decision rule engine for PHP shared hosting. It combines a raw JSON evaluation API with a lightweight risk-management administration interface. Credit rules and their structured conditions live entirely in MySQL/MariaDB—adding a rule or input field never requires a PHP code change.

## Requirements

- PHP 8.0 or newer with `pdo_mysql`, JSON, and session support
- MySQL 5.7+ or MariaDB 10.3+
- A standard web server (Apache/nginx or the PHP development server)

No framework, Composer, Node.js, build step, container, or external CDN is required.

## Installation

1. Create an empty database, for example `decision_rules`.
2. Import the schema and 16 demo rules:
   ```bash
   mysql -u database_user -p decision_rules < sql/database.sql
   ```
3. Copy the example configuration and enter the database credentials:
   ```bash
   cp config/config.example.php config/config.php
   ```
   `config/config.php` is ignored by Git. Keep it outside public source control.
4. Point the site's document root at this directory. Opening `/` redirects to the admin dashboard.

If configuration is absent, the admin shows a setup message and the API returns a safe `CONFIGURATION_ERROR` JSON response. Database failures never expose a DSN, password, SQL, or stack trace.

## Local development

After importing the database and configuring it:

```bash
php -S 127.0.0.1:8080
```

Open <http://127.0.0.1:8080/admin/>. The API tester can construct requests visually.

## Evaluation API

Send any input fields as GET parameters:

```text
/evaluate.php?AGE=35&DEBT_SERVICE_TO_INCOME_RATIO_PERCENT=31&HAS_ESTATE=No&BANK_CREDIT_ACCOUNTS_COUNT=1
```

The endpoint always responds as `application/json; charset=utf-8`. Conditions within one rule use AND. Active rules are checked in hard-refusal, risk-review, then segmentation order, with priority and ID as tie breakers. A missing required parameter makes that condition false and appears once in `missing_fields`.

Example response:

```json
{
  "success": true,
  "decision": "APPROVE",
  "stage": "PORTFOLIO_SEGMENTATION_STAGE",
  "matched_rule": {"rule_code": "PS_003", "stage_name": "PORTFOLIO_SEGMENTATION_STAGE", "avg_actual_pd": 2.15, "priority": 30},
  "matched_rules": [],
  "missing_fields": ["AGE"],
  "meta": {"rules_checked": 16, "execution_time_ms": 1.2}
}
```

Supported operators are `>`, `>=`, `<`, `<=`, `=`, `!=`, `IN`, `NOT_IN`, `IS_NULL`, and `IS_NOT_NULL`. Numeric inequalities are explicitly numeric. `IN` lists can be JSON arrays such as `["SALARY","PENSION"]` or single values. No stored value is ever evaluated as PHP or SQL.

## Administration

- **Dashboard** — portfolio counts and recently updated rules
- **Rules** — view, edit, activate/deactivate, or delete rules
- **Add Rule** — dynamic structured AND-condition builder
- **API Tester** — build a request, inspect formatted JSON, copy its URL, or open raw JSON

All writes use POST, prepared statements, validation, output escaping, and a session CSRF token. For an internet-facing production deployment, protect `/admin/` with hosting-level authentication or another access-control layer.

## Project structure

```text
admin/       Admin pages and shared layout partials
assets/      Local CSS and vanilla JavaScript
config/      Committable example configuration (real config is ignored)
src/         PDO connection, repository, and generic rule engine
sql/         Schema, indexes, foreign key, and demo seed data
tests/       Dependency-free rule engine checks
evaluate.php Raw JSON API endpoint
```

## Shared hosting deployment (including TopHost)

Upload the repository contents into the selected domain/subdomain document root, create a MySQL/MariaDB database in the hosting control panel, import `sql/database.sql` with the panel's database tool, and upload a completed `config/config.php`. Ensure PHP 8+ and `pdo_mysql` are selected. No shell access or deployment process is necessary. Consider denying web access to `config/`, `src/`, `sql/`, and `tests/` in the hosting panel; PHP source is normally not served, but those directories need not be public.

## Checks

```bash
find . -name '*.php' -not -path './config/config.php' -print0 | xargs -0 -n1 php -l
php tests/RuleEngineTest.php
```
