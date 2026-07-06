---
globs:
  - "**/schema.graphqls"
  - "**/Resolver/**"
priority: 50
---

# GraphQL Rules

## Schema
- Extend `type Query` / `type Mutation` in `etc/schema.graphqls` — never modify Magento core's schema files directly
- One resolver class per field; resolver name matches field name in PascalCase
- Mark deprecated fields with `@deprecated(reason: "...")`, never remove fields a mobile client may depend on

## Resolvers
- Implement `\Magento\Framework\GraphQl\Query\ResolverInterface` (or `BatchResolverInterface`/`BatchServiceContractResolverInterface` for list fields, to avoid N+1 queries)
- Constructor injection only — same DI rules as everywhere else in this module
- Resolvers return arrays matching the schema type, never Magento model/data objects directly

## Errors
- Throw `GraphQlInputException` for invalid input, `GraphQlNoSuchEntityException` for missing entities, `GraphQlAuthorizationException` for permission failures
- Never leak internal exception messages or stack traces in the `errors` array

## Auth & Caching
- Customer-scoped fields must check `$context->getUserId()` / the customer token from `generateCustomerToken` — GraphQL auth is token-based, not session-based like the storefront
- Cacheable query resolvers should implement `IdentityInterface` and declare cache tags so full-page cache varies correctly
- Mutations are never cached — do not add `@cache` directives to `type Mutation` fields

## Mobile & Frontend Consumption
- Design queries to minimize round-trips: nest related fields rather than requiring multiple sequential queries
- Use connection-style pagination (edges/nodes, pageInfo) for lists consumed by infinite-scroll UIs
- Add new fields as nullable/additive; never change or remove existing field types — mobile clients in the field cannot always update immediately
- Expose only the fields the client actually needs — don't mirror Magento's full EAV attribute set by default
