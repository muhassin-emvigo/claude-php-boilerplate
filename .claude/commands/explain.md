---
description: Explain a file, class, method, or code section in plain language
allowed-tools:
  - Read
  - Grep
  - Bash
---

# Explain Code

1. Read the specified file or code section

2. Provide a clear explanation covering:
   - **Purpose**: What does this code do and why does it exist?
   - **How it works**: Step-by-step logic flow
   - **Magento context**: How it fits within Magento's architecture
     - Is it a Plugin, Observer, Controller, Model, ViewModel?
     - What DI dependencies does it use and why?
     - What events/hooks does it interact with?
   - **Dependencies**: What does it depend on? What depends on it?
   - **Configuration**: Related XML config files (`di.xml`, `events.xml`, etc.)
   - **Side effects**: Database changes, cache invalidation, event dispatching

3. If relevant, also note:
   - Potential improvements or code smells
   - Security considerations
   - Performance implications
   - Related files that work together with this code

4. Use analogies and simple language — assume the reader is familiar with PHP but may be new to Magento 2
