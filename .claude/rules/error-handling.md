---
globs:
  - "**/*.php"
---

# Error Handling Rules

## Exception Classes
- Use Magento's built-in exception hierarchy — never throw generic `\Exception`:
  - `LocalizedException` — user-facing errors with translatable messages
  - `NoSuchEntityException` — entity not found (404 equivalent)
  - `CouldNotSaveException` — save operation failed
  - `CouldNotDeleteException` — delete operation failed
  - `InputException` — invalid input/parameters
  - `AuthorizationException` — permission denied
  - `AuthenticationException` — authentication failed
  - `StateException` — invalid object state for operation
  - `ValidatorException` — validation failure

## Patterns
- Catch specific exceptions, never bare `catch (\Exception $e)` unless re-throwing
- Always log exceptions before re-throwing or converting:
  ```php
  try {
      $this->resource->save($entity);
  } catch (\Exception $e) {
      $this->logger->error($e->getMessage(), ['exception' => $e]);
      throw new CouldNotSaveException(__('Could not save entity: %1', $e->getMessage()), $e);
  }
  ```
- Use `__()` for all user-facing error messages (translation support)
- Never swallow exceptions silently (empty catch blocks)
- Preserve original exception as `$previous` parameter when wrapping

## Logging
- Use `Psr\Log\LoggerInterface` via constructor injection
- Log levels:
  - `emergency`, `alert`, `critical` — system is unusable
  - `error` — runtime errors, exceptions
  - `warning` — unusual but not error conditions
  - `notice`, `info` — normal events worth recording
  - `debug` — detailed debugging (disable in production)
- Never log sensitive data (passwords, credit cards, tokens, PII)
- Include context array with relevant data: `$this->logger->error('msg', ['order_id' => $id])`

## API Error Responses
- Controllers: set proper HTTP status codes (400, 404, 500)
- Web API: throw appropriate Magento exceptions — framework handles HTTP mapping
- Never expose stack traces or internal paths to end users
- Return structured error messages with error codes
