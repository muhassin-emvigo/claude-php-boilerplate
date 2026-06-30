---
name: phpunit-test
description: >
  Write PHPUnit tests for Magento 2 modules. Creates unit tests with
  proper mocking patterns and integration tests with Magento fixtures.
  Use when asked to write, create, or add tests.
---

# PHPUnit Test Writing for Magento 2

## Unit Test Pattern

### Structure
```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Vendor\Module\Model\MyClass;
use Vendor\Module\Api\Data\EntityInterface;

class MyClassTest extends TestCase
{
    private MyClass $subject;
    private EntityInterface&MockObject $entityMock;

    protected function setUp(): void
    {
        $this->entityMock = $this->createMock(EntityInterface::class);
        $this->subject = new MyClass($this->entityMock);
    }

    public function testMethodName_WhenValidInput_ReturnsExpected(): void
    {
        // Arrange
        $this->entityMock->method('getId')->willReturn(1);

        // Act
        $result = $this->subject->methodName();

        // Assert
        $this->assertSame('expected', $result);
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testMethodName_WithInvalidInput_ThrowsException(mixed $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subject->methodName($input);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'null input' => [null],
            'empty string' => [''],
            'negative number' => [-1],
        ];
    }
}
```

### Common Magento Mocking Patterns

#### Mock a Repository
```php
$repositoryMock = $this->createMock(EntityRepositoryInterface::class);
$repositoryMock->method('getById')
    ->with(1)
    ->willReturn($entityMock);
```

#### Mock SearchCriteria
```php
$searchCriteriaMock = $this->createMock(SearchCriteriaInterface::class);
$searchResultsMock = $this->createMock(SearchResultsInterface::class);
$searchResultsMock->method('getItems')->willReturn([$entityMock]);
$repositoryMock->method('getList')->willReturn($searchResultsMock);
```

#### Mock Config Values
```php
$scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
$scopeConfigMock->method('getValue')
    ->with('section/group/field', ScopeInterface::SCOPE_STORE)
    ->willReturn('config_value');
```

## Integration Test Pattern
```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\Integration\Model;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class EntityRepositoryTest extends TestCase
{
    private EntityRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()
            ->get(EntityRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Vendor_Module::Test/Integration/_files/entity.php
     */
    public function testGetById_WithExistingEntity_ReturnsEntity(): void
    {
        $entity = $this->repository->getById(1);
        $this->assertInstanceOf(EntityInterface::class, $entity);
    }
}
```

## Checklist Before Writing Tests
1. Identify the class under test and all its constructor dependencies
2. Determine which methods need testing (public API)
3. List test scenarios: happy path, edge cases, error conditions
4. Mock all dependencies — never instantiate real Magento classes in unit tests
5. Write tests following AAA pattern
6. Run and verify: `vendor/bin/phpunit --filter=MyClassTest`
