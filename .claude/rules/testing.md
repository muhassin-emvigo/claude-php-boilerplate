---
globs:
  - "**/*Test.php"
  - "**/Test/**/*.php"
priority: 60
---

# Testing Rules

## Naming Convention
- Test class: `{ClassName}Test` in matching namespace under `Test/Unit/` or `Test/Integration/`
- Test method: `test<MethodName>_<Scenario>_<ExpectedBehavior>()`
- Example: `testGetName_WithValidEntity_ReturnsName()`

## Unit Tests
- Mock ALL constructor dependencies using `$this->createMock()`
- Use `setUp()` to initialize subject and mocks
- One assertion concept per test method
- Use data providers for parameterized tests with `@dataProvider` annotation
- Never access database, filesystem, or network in unit tests
- Test both happy path and error/edge cases
- Use `expectException()` for testing exception scenarios

## Integration Tests
- Use `@magentoDbIsolation enabled` to rollback DB changes
- Use `@magentoAppIsolation enabled` for app state isolation
- Use `@magentoDataFixture` for test data setup
- Use `@magentoConfigFixture` to set config values
- Fixture files go in `Test/Integration/_files/`

## Structure
- Follow Arrange-Act-Assert (AAA) pattern
- Keep tests focused and independent — no test should depend on another's state
- Use descriptive variable names in tests: `$expectedResult`, `$actualResult`
- Group related test methods together in the test class
