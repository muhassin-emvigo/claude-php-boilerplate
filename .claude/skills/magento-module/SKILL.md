---
name: magento-module
description: >
  Scaffold a new Magento 2 module with proper structure, registration,
  configuration, and test setup. Use when asked to create, scaffold,
  or generate a new Magento 2 module.
---

# Magento 2 Module Scaffolding

## Steps to Create a New Module

### 1. Gather Information
Ask the user for:
- **vendor name** (e.g., `Acme`)
- **Module name** (e.g., `CustomShipping`)
- **Module purpose** (brief description)
- **Dependencies** (which Magento modules it depends on)
- **Features needed**: API, Admin UI, Frontend, Cron, CLI commands

### 2. Create Base Structure
```
app/code/{vendor}/{Module}/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   └── di.xml
└── Test/
    └── Unit/
        └── .gitkeep
```

### 3. registration.php Template
```php
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    '{vendor}_{Module}',
    __DIR__
);
```

### 4. etc/module.xml Template
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{vendor}_{Module}">
        <sequence>
            <!-- Add dependencies here -->
        </sequence>
    </module>
</config>
```

### 5. Add Feature Directories
Based on user requirements, create:
- `Api/` + `Api/Data/` — for service contracts
- `Model/` + `Model/ResourceModel/` — for data models
- `Controller/` — for routes
- `Block/` or `ViewModel/` — for frontend
- `Observer/` — for event handling
- `Plugin/` — for interceptors
- `Setup/Patch/Data/` — for data patches
- `etc/db_schema.xml` — for database tables
- `view/frontend/` or `view/adminhtml/` — for templates and layouts
- `Cron/` — for cron jobs
- `Console/Command/` — for CLI commands

### 6. Create Tests
- Create `Test/Unit/` mirroring the module structure
- Write at least one test per model/service class
- Follow AAA pattern and naming conventions

### 7. Validate
```bash
php -l app/code/{vendor}/{Module}/registration.php
vendor/bin/phpcs --standard=Magento2 app/code/{vendor}/{Module}/
vendor/bin/phpstan analyse app/code/{vendor}/{Module}/
```
