# Making Business Logic Changes (For Developers)

This explains where to make changes, the steps to see them take effect, and how to reuse this same setup pattern for other projects.

## Where your own code lives

All custom business logic belongs in one place:

```
app/code/vendor/CustomShipping/
```

Everything under `vendor/` (lowercase, at the project root) is Magento's own engine — never edit files there directly; any change would be lost/overwritten on the next `composer update`.

Typical places to add logic inside `app/code/vendor/CustomShipping/`:

| Folder | What goes there |
|---|---|
| `Api/` | Interfaces describing what your code offers (contracts) |
| `Model/` | The actual business logic / data handling |
| `Controller/` | Code that responds to a URL (storefront or admin) |
| `Plugin/` | Code that hooks into existing Magento behavior without editing it |
| `Observer/` | Code that reacts to Magento "events" (e.g. "order placed") |
| `etc/` | Configuration XML files (already has `module.xml`) |

## Step-by-step: making a change

1. **Edit or add a PHP file** under `app/code/vendor/CustomShipping/`.
2. **If you added a brand-new class**, refresh Magento's generated code:
   ```bash
   php bin/magento setup:di:compile
   ```
3. **If you changed a database table/schema**, run:
   ```bash
   php bin/magento setup:upgrade
   ```
4. **Always clear the cache** after a change, or Magento may keep showing old behavior:
   ```bash
   php bin/magento cache:flush
   ```
5. **Reload the page in your browser** and confirm the change is visible.
6. **Run the checks** described in [03-testing-the-site.md](03-testing-the-site.md) before considering the change done.

## Quality checks before committing

```bash
make lint      # coding standard
make phpstan   # static analysis
make test      # unit tests
```

> **Known issue:** the Git `pre-commit` hook (CaptainHook) currently fails on this Windows machine with `Unable to launch a new process`. Until that's fixed, either run `make lint`/`make phpstan`/`make test` manually before committing, or ask for help resolving the hook. Do not disable safety checks permanently without a plan to fix them.

## Reusing this setup for a different project

This project's structure (Makefile, `install-magento-xampp.ps1`, `start-magento.ps1`, `docs/`) is a reusable pattern for any Magento 2 module project on Windows/XAMPP:

1. Copy the whole project folder to a new location inside `C:\xampppp\htdocs\`.
2. Run `make init` to rename the placeholder module (`vendor/CustomShipping`) to your new module's name.
3. Update these files to match the new project's folder name (search for `rgd_dental` and replace it):
   - `install-magento-xampp.ps1` (the `$baseUrl` and `.htaccess` content)
   - `start-magento.ps1` (the final `Start-Process` URL)
   - The Apache `Alias` block in `C:\xampppp\apache\conf\extra\httpd-xampp.conf`
4. Follow [01-getting-started.md](01-getting-started.md) again for the new project.

Everything else — the docs structure, the Makefile targets, the quality-check setup — carries over unchanged.
