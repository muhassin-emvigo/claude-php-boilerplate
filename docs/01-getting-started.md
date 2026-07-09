# Getting Started (No Technical Knowledge Needed)

This guide is for anyone who just wants to **open the website** and look at it — you do not need to know any coding.

## What is this?

This folder holds a website built on **Magento**, a popular platform for online shops. To see the website on your own computer, three background programs need to be running:

1. **The database** — stores all the website's information (products, customers, etc.)
2. **The search engine** — powers the search box on the website
3. **The web server** — actually shows the website in your browser

Normally you'd have to start all three yourself. Instead, there is one script that starts everything for you.

## Step 1: Start everything

1. Open the `rgd_dental` folder in File Explorer.
2. Find the file named **`start-magento.ps1`**.
3. Right-click it and choose **"Run with PowerShell"**.
   - If Windows asks "Are you sure you want to run this script?", click **Yes** / **Run**.
4. A black window will open and show progress messages like:
   ```
   == Starting the database (MySQL) ==
   Database is ready.

   == Starting the search engine (OpenSearch) ==
   Search engine is ready.

   == Starting the web server (Apache) ==
   Web server is ready.
   ```
5. When it's done, your web browser will open the website automatically.

**Wait for it:** the search engine can take up to a minute to start the first time. That's normal — just wait for the black window to say "Search engine is ready."

## Step 2: Look at the website

Once the script finishes, the website opens by itself at:

```
http://localhost/rgd_dental/
```

If it didn't open automatically, just copy that address into your browser.

## Step 3: Look at the admin panel (the "back office")

The admin panel is where you manage products, orders, and settings. Open:

```
http://localhost/rgd_dental/admin
```

Log in with:
- **Username:** `admin`
- **Password:** `Admin123!`

## Do I need to do this every time?

Yes — every time you restart your computer, the three background programs stop, and you'll need to run `start-magento.ps1` again before the website will work. You do **not** need to reinstall anything; this script only starts things that are already installed.

## Something not working?

See [03-testing-the-site.md](03-testing-the-site.md) for simple checks you can do yourself, and share the results with your developer if something looks wrong.
