---
globs:
  - "**/Api/**"
  - "**/webapi.xml"
  - "**/Controller/**"
priority: 50
---

# API & Web API Rules

## Service Contracts
- Define all public APIs as interfaces in `Api/` directory
- Data interfaces go in `Api/Data/` — pure DTOs with getters/setters
- Mark public API interfaces with `@api` PHPDoc annotation
- Repository interfaces: `save()`, `getById()`, `delete()`, `getList(SearchCriteriaInterface)`
- Return interfaces, never concrete classes, from API methods

## REST/SOAP Endpoints (`webapi.xml`)
- Define routes in `etc/webapi.xml`:
  ```xml
  <route url="/V1/vendor-module/entities/:id" method="GET">
      <service class="Vendor\Module\Api\EntityRepositoryInterface" method="getById"/>
      <resources>
          <resource ref="Vendor_Module::manage"/>
      </resources>
  </route>
  ```
- Use proper HTTP methods: GET (read), POST (create), PUT (update), DELETE (remove)
- Version API URLs: `/V1/...`
- Always specify ACL resource — never use `anonymous` or `self` without justification
- Use `SearchCriteriaInterface` for list endpoints, return `SearchResultsInterface`

## Request/Response Design
- Input: validate all parameters with type declarations and explicit checks
- Output: return data interface objects, never raw arrays
- Pagination: support `searchCriteria[pageSize]` and `searchCriteria[currentPage]`
- Filtering: support `searchCriteria[filterGroups]` with field/value/conditionType
- Sorting: support `searchCriteria[sortOrders]` with field/direction

## Controller Actions
- Frontend controllers extend `\Magento\Framework\App\Action\Action`
- Admin controllers extend `\Magento\Backend\App\Action`
- One action per controller class (single `execute()` method)
- Admin controllers must declare `const ADMIN_RESOURCE = 'Vendor_Module::resource'`
- Return proper result types: `JsonFactory`, `PageFactory`, `RedirectFactory`, `RawFactory`
- Use `RequestInterface` for input — never `$_GET`, `$_POST`, `$_REQUEST`
