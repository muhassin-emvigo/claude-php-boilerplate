# Testing That Everything Works

Use this checklist any time after running `start-magento.ps1`, to confirm the site is actually working before you rely on it.

## Simple checklist (no technical knowledge needed)

Open each address below in your browser and check what you should see:

| # | Open this address | You should see |
|---|---|---|
| 1 | `http://localhost/rgd_dental/` | The website's home page, fully styled (not plain text, not an error page) |
| 2 | `http://localhost/rgd_dental/customer/account/login` | A "Customer Login" page with email/password boxes |
| 3 | `http://localhost/rgd_dental/admin` | An admin login page (background office login) |
| 4 | Log into the admin page (`admin` / `Admin123!`) | A dashboard with charts and menus on the left |

If any of these show a **plain white/grey error page**, an **Apache "It works!" page**, or the text **"Magento is not installed yet"**, something isn't running — go back to [01-getting-started.md](01-getting-started.md) and re-run `start-magento.ps1`.

## For developers: quick command-line checks

If you have a terminal open in the `rgd_dental` folder, these commands confirm each piece is actually responding, and print an HTTP status code (`200` = good, anything else = a problem):

```bash
curl -s -o /dev/null -w "Homepage: %{http_code}\n"      "http://localhost/rgd_dental/"
curl -s -o /dev/null -w "Customer login: %{http_code}\n" "http://localhost/rgd_dental/customer/account/login"
curl -sL -o /dev/null -w "Admin: %{http_code}\n"          "http://localhost/rgd_dental/admin"
```

Check the background programs are running:

```powershell
Get-Process httpd, mysqld -ErrorAction SilentlyContinue   # Apache, MySQL
Test-NetConnection -ComputerName 127.0.0.1 -Port 9200      # OpenSearch
```

Check for errors in the logs (most recent lines shown):

```bash
tail -n 30 var/log/system.log       # Magento application errors
tail -n 30 var/log/exception.log    # Uncaught exceptions
tail -n 15 /c/xampppp/apache/logs/error.log   # Web server errors
```

## Running the automated code checks

Before committing code changes, the project's own quality checks can be run with:

```bash
make lint      # coding standard check
make phpstan   # static analysis
make test      # unit tests
```

(These currently have a known issue with the pre-commit Git hook on Windows — see the note at the bottom of [04-making-business-logic-changes.md](04-making-business-logic-changes.md).)

Next: [04-making-business-logic-changes.md](04-making-business-logic-changes.md) — where to make changes as a developer.
