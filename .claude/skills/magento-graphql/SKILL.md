---
name: magento-graphql
description: >
  Extend Magento 2's GraphQL API with custom schema types, queries,
  mutations, and resolvers for headless mobile apps and frontend clients.
  Use when asked to add a GraphQL query/mutation, expose module data over
  GraphQL, or build a headless/mobile-facing API on top of this module.
---

# Magento 2 GraphQL API (Headless — Mobile & Frontend)

Magento's GraphQL module is an API layer on top of the existing MySQL-backed
persistence layer — it does not replace MySQL, it exposes it. Core Magento
(catalog, customers, orders) still runs on MySQL underneath; this skill is
for extending the GraphQL surface your mobile app / frontend actually talks
to, not for changing what's underneath it.

## When to use this
- Adding a new query or mutation for mobile/frontend consumption
- Exposing this module's custom entities over GraphQL instead of REST
- Reviewing or fixing an existing resolver

## Core Files & Pattern

1. **Schema**: `etc/schema.graphqls` — extend `type Query` / `type Mutation`,
   never edit Magento core's schema files directly.
   ```graphql
   type Query {
       vendorModuleEntity(id: Int! @doc(description: "Entity ID")): VendorModuleEntity
           @resolver(class: "Vendor\\Module\\Model\\Resolver\\Entity")
           @doc(description: "Get a single entity by ID")
   }

   type VendorModuleEntity @doc(description: "Entity data") {
       id: Int!
       name: String
       created_at: String
   }
   ```

2. **Resolver**: implement `\Magento\Framework\GraphQl\Query\ResolverInterface`
   for single items, or `BatchResolverInterface` / `BatchServiceContractResolverInterface`
   for list fields — batch resolvers avoid N+1 query problems when a parent
   query returns many rows that each need this field resolved.

3. **Constructor injection only** — same DI rules as REST controllers and
   everywhere else in this module. Resolvers are just another DI-managed class.

4. **Return arrays, not objects** — resolvers must return arrays matching the
   schema type's fields, never Magento model/data objects directly.

## Errors
- `GraphQlInputException` — invalid input/arguments
- `GraphQlNoSuchEntityException` — entity not found
- `GraphQlAuthorizationException` — permission denied
- Never leak internal exception messages or stack traces into the `errors` array in the response

## Auth & Caching
- GraphQL auth is **token-based**, not session-cookie-based like the storefront.
  Customer-scoped fields must check `$context->getUserId()` / the customer
  token from `generateCustomerToken`, not web session state.
- Cacheable query resolvers should implement `IdentityInterface` and declare
  cache tags so full-page cache varies correctly per entity.
- Mutations are never cached — do not add `@cache` directives to `type Mutation` fields.

## Mobile & Frontend Consumption — Design Rules
- **Minimize round-trips.** Nest related fields into one query instead of
  requiring the client to issue several sequential queries (this is the
  main reason to use GraphQL over REST for mobile — don't waste it by
  designing a REST-shaped schema).
- **Paginate with connections.** Use edges/nodes + `pageInfo` for any list a
  mobile client will infinite-scroll, matching the pattern Magento's own
  `products` query uses.
- **Additive schema evolution only.** Mobile apps in the field cannot always
  update immediately. Add new nullable fields; never change an existing
  field's type or remove a field a shipped app may depend on. Mark anything
  genuinely retired with `@deprecated(reason: "...")` instead of deleting it.
- **Fetch only what the client needs.** Don't expose Magento's full EAV
  attribute set by default — define a purpose-built type for mobile/frontend
  consumption rather than mirroring the internal data model field-for-field.

## Testing
- Use `\Magento\TestFramework\TestCase\GraphQlAbstract` for integration tests —
  send raw GraphQL query strings and assert on the decoded response array.
- Cover: happy path, missing/invalid ID (`GraphQlNoSuchEntityException`),
  unauthenticated access to a customer-scoped field
  (`GraphQlAuthorizationException`), and pagination boundaries.

## Anti-patterns
- Do not use a class preference/rewrite on core `Query`/`Mutation` resolvers —
  only extend via your own `schema.graphqls` additions.
- Do not bypass the auth checks available on `$context` "just to get it working."
- Do not treat this as a path to replacing MySQL — GraphQL is the API surface,
  Magento's persistence layer underneath is unchanged.
