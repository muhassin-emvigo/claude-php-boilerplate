# How This Website Is Put Together

A simple explanation of the moving parts, for anyone curious how it all fits together (still no coding needed to read this).

## The three background programs

| Program | What it does | Where it lives |
|---|---|---|
| **MySQL** | Stores all data: products, orders, customers, settings | `C:\xampppp\mysql` |
| **OpenSearch** | Makes the search box on the website fast and smart | `C:\opensearch\opensearch-2.11.0` |
| **Apache** | The web server — takes browser requests and shows the website | `C:\xampppp\apache` |

`start-magento.ps1` (see [01-getting-started.md](01-getting-started.md)) turns all three of these on in the right order.

## The website software itself

The website runs on **Magento**, a large, ready-made piece of shop software. Nearly all of it lives in a folder called `vendor/` — this is the "engine" and you should never need to edit it directly.

The parts that are **specific to this project** (our own custom code) live in:

```
app/code/vendor/CustomShipping/
```

This is where a developer would add or change **business logic** — things like custom shipping rules, special pricing, or new features. See [04-making-business-logic-changes.md](04-making-business-logic-changes.md) for that.

## The web address

- The website is only visible on **this computer**, at `http://localhost/rgd_dental/`. It is not visible to anyone on the internet.
- `localhost` means "this computer." Nobody outside your machine can open this address.

## A quick map of important folders

```
rgd_dental/
├── app/code/vendor/CustomShipping/   <- our custom code (business logic) lives here
├── vendor/                           <- the Magento "engine" (don't edit this)
├── pub/                              <- the folder the web server actually shows to visitors
├── docs/                             <- you are here
├── start-magento.ps1                 <- starts everything (see doc 01)
└── install-magento-xampp.ps1         <- only needed once, for the very first setup
```

Next: [03-testing-the-site.md](03-testing-the-site.md) — how to check everything is actually working.
