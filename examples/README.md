# Doctrix Examples

This directory contains comprehensive examples demonstrating all Doctrix features. Each file focuses on a specific aspect of the library.

## Examples Index

### Core Features
- **[01-basic-usage.php](01-basic-usage.php)** - Basic query operations, fetch methods, and criteria
- **[02-fluent-interface.php](02-fluent-interface.php)** - Fluent query builder API and method chaining
- **[03-pagination.php](03-pagination.php)** - Pagination methods and result handling
- **[04-repository-patterns.php](04-repository-patterns.php)** - Repository design patterns and best practices
- **[05-service-pattern.php](05-service-pattern.php)** - Using Doctrix as a service instead of inheritance
- **[06-symfony-integration.php](06-symfony-integration.php)** - Integration with Symfony framework

### Migration & Setup
- **[07-migration-guide.md](07-migration-guide.md)** - Guide for migrating from legacy repositories

### Advanced Features
- **[08-fetch-or-fail.php](08-fetch-or-fail.php)** - Error handling patterns (fetchOneOrFail, fetchOneOrCreate, sole)
- **[09-chunk-processing.php](09-chunk-processing.php)** - Memory-efficient processing (chunk, each, lazy, batchProcess)
- **[10-existence-checks.php](10-existence-checks.php)** - Existence validation (exists, hasExactly, hasAtLeast)
- **[11-bulk-operations.php](11-bulk-operations.php)** - Bulk updates and deletes without fetching entities
- **[12-persistent-filters.php](12-persistent-filters.php)** - Filters that persist across operations like pagination
- **[13-macros.php](13-macros.php)** - Custom reusable query methods
- **[14-request-queries.php](14-request-queries.php)** - Building secure queries from HTTP requests
- **[15-debugging.php](15-debugging.php)** - Query debugging and performance analysis

## Running Examples

Each PHP example can be run standalone:

```bash
# Run a specific example
php examples/01-basic-usage.php

# Or include in your code
require_once 'vendor/welshdev/doctrix/examples/01-basic-usage.php';
```

## Example Structure

Each example file typically contains:
1. **Use case classes** - Real-world service implementations
2. **Repository examples** - Custom repository methods
3. **Usage demonstrations** - How to use the features
4. **Best practices** - Tips and recommendations

## Quick Start

If you're new to Doctrix, we recommend reviewing the examples in order:
1. Start with [01-basic-usage.php](01-basic-usage.php)
2. Learn the fluent interface in [02-fluent-interface.php](02-fluent-interface.php)
3. Understand pagination with [03-pagination.php](03-pagination.php)
4. Choose your pattern: [04-repository-patterns.php](04-repository-patterns.php) or [05-service-pattern.php](05-service-pattern.php)

## Advanced Topics

For specific use cases:
- **Large datasets**: See [09-chunk-processing.php](09-chunk-processing.php)
- **Error handling**: See [08-fetch-or-fail.php](08-fetch-or-fail.php)
- **Complex filters**: See [12-persistent-filters.php](12-persistent-filters.php)
- **API endpoints**: See [14-request-queries.php](14-request-queries.php)
- **Performance**: See [15-debugging.php](15-debugging.php)