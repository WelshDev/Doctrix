<?php
/**
 * Macro Examples
 * 
 * This file demonstrates how to register and use custom reusable query methods (macros)
 * for creating domain-specific query shortcuts.
 */

use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;

// Example 1: Basic Macro Registration
// ====================================
// Register a simple macro on a repository
$userRepo->registerMacro('activeAdmins', function(FluentQueryBuilder $query) {
    return $query
        ->where('status', 'active')
        ->where('role', 'admin')
        ->orderBy('name', 'ASC');
});

// Use the macro
$admins = $userRepo->query()->activeAdmins()->get();

// Example 2: Multiple Macro Registration
// ======================================
$userRepo->registerMacros([
    'active' => fn($q) => $q->where('status', 'active'),
    'verified' => fn($q) => $q->where('emailVerified', true),
    'premium' => fn($q) => $q->where('subscriptionType', 'premium'),
    'recent' => fn($q) => $q->where('createdAt', '>=', new DateTime('-30 days'))
]);

// Chain multiple macros
$users = $userRepo->query()
    ->active()
    ->verified()
    ->premium()
    ->get();

// Example 3: Parameterized Macros
// ================================
$productRepo->registerMacro('inPriceRange', function(FluentQueryBuilder $query, $min, $max) {
    return $query->whereBetween('price', $min, $max);
});

$productRepo->registerMacro('inCategory', function(FluentQueryBuilder $query, $category) {
    return $query->where('category', $category);
});

// Use with parameters
$products = $productRepo->query()
    ->inCategory('electronics')
    ->inPriceRange(100, 500)
    ->get();

// Example 4: Complex Business Logic Macros
// ========================================
$orderRepo->registerMacro('readyToShip', function(FluentQueryBuilder $query) {
    return $query
        ->where('status', 'paid')
        ->where('inventoryChecked', true)
        ->whereNotNull('shippingAddress')
        ->where('fraudCheck', 'passed')
        ->orderBy('priority', 'DESC')
        ->orderBy('createdAt', 'ASC');
});

$ordersToShip = $orderRepo->query()->readyToShip()->limit(100)->get();

// Example 5: Time-based Macros
// ============================
$userRepo->registerMacros([
    'createdToday' => fn($q) => $q->where('createdAt', '>=', new DateTime('today')),
    'createdYesterday' => fn($q) => $q->whereBetween('createdAt', 
        new DateTime('yesterday'), 
        new DateTime('today')
    ),
    'createdThisWeek' => fn($q) => $q->where('createdAt', '>=', new DateTime('monday this week')),
    'createdThisMonth' => fn($q) => $q->where('createdAt', '>=', new DateTime('first day of this month'))
]);

$todaysUsers = $userRepo->query()->createdToday()->get();
$thisWeeksUsers = $userRepo->query()->createdThisWeek()->count();

// Example 6: Repository-Specific Macros in Class
// ==============================================
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry);
        
        // Register default macros for this repository
        $this->registerMacros([
            'active' => fn($q) => $q->where('status', 'active'),
            'inactive' => fn($q) => $q->where('status', 'inactive'),
            'suspended' => fn($q) => $q->where('status', 'suspended'),
            'withProfile' => fn($q) => $q->whereNotNull('profileId'),
            'admins' => fn($q) => $q->where('role', 'admin'),
            'moderators' => fn($q) => $q->where('role', 'moderator'),
            'verified' => fn($q) => $q->where('emailVerified', true),
            'unverified' => fn($q) => $q->where('emailVerified', false),
            'recentlyActive' => fn($q) => $q->where('lastLogin', '>=', new DateTime('-7 days'))
        ]);
    }
    
    // Custom method that uses macros
    public function getActiveAdmins(): array
    {
        return $this->query()
            ->active()
            ->admins()
            ->verified()
            ->get();
    }
}

// Example 7: Global Macros for All FluentQueryBuilders
// ====================================================
// Register global macros that apply to all query builders
FluentQueryBuilder::registerGlobalMacro('recent', function(FluentQueryBuilder $query, $days = 7) {
    return $query->where('createdAt', '>=', new DateTime("-{$days} days"));
});

FluentQueryBuilder::registerGlobalMacro('orderByLatest', function(FluentQueryBuilder $query, $field = 'createdAt') {
    return $query->orderBy($field, 'DESC');
});

// Now available on all repositories
$recentUsers = $userRepo->query()->recent(30)->orderByLatest()->get();
$recentOrders = $orderRepo->query()->recent(1)->orderByLatest('orderDate')->get();

// Example 8: Conditional Macros
// =============================
$productRepo->registerMacro('applyFilters', function(FluentQueryBuilder $query, array $filters) {
    if (isset($filters['category'])) {
        $query->where('category', $filters['category']);
    }
    
    if (isset($filters['minPrice'])) {
        $query->where('price', '>=', $filters['minPrice']);
    }
    
    if (isset($filters['maxPrice'])) {
        $query->where('price', '<=', $filters['maxPrice']);
    }
    
    if (isset($filters['inStock']) && $filters['inStock']) {
        $query->where('stock', '>', 0);
    }
    
    if (isset($filters['search'])) {
        $query->where('name', 'like', '%' . $filters['search'] . '%');
    }
    
    return $query;
});

// Use with request data
$filters = $_GET; // or $request->query->all() in Symfony
$products = $productRepo->query()
    ->applyFilters($filters)
    ->paginate(1, 20);

// Example 9: Combining Macros with Regular Methods
// ================================================
$userRepo->registerMacro('premiumUsers', function(FluentQueryBuilder $query) {
    return $query
        ->where('subscriptionType', 'premium')
        ->where('subscriptionStatus', 'active');
});

// Combine macro with regular query methods
$users = $userRepo->query()
    ->premiumUsers()  // Macro
    ->where('country', 'US')  // Regular method
    ->whereIn('state', ['CA', 'NY', 'TX'])  // Regular method
    ->orderBy('revenue', 'DESC')  // Regular method
    ->limit(100)  // Regular method
    ->get();

// Example 10: Macro Composition
// =============================
$orderRepo->registerMacro('highValue', function(FluentQueryBuilder $query) {
    return $query->where('total', '>=', 1000);
});

$orderRepo->registerMacro('vipCustomer', function(FluentQueryBuilder $query) {
    return $query->whereIn('customerTier', ['gold', 'platinum']);
});

$orderRepo->registerMacro('priorityOrders', function(FluentQueryBuilder $query) {
    // Compose other macros
    return $query
        ->highValue()
        ->vipCustomer()
        ->where('rushDelivery', true);
});

$priorityOrders = $orderRepo->query()->priorityOrders()->get();

// Example 11: Dynamic Macro Registration
// ======================================
// Register macros based on configuration
$statusMacros = [];
foreach (['draft', 'pending', 'approved', 'rejected'] as $status) {
    $statusMacros[$status] = function(FluentQueryBuilder $query) use ($status) {
        return $query->where('status', $status);
    };
}
$documentRepo->registerMacros($statusMacros);

// Now you can use: ->draft(), ->pending(), ->approved(), ->rejected()
$pendingDocs = $documentRepo->query()->pending()->get();

// Example 12: Removing and Managing Macros
// ========================================
// Check if macro exists
if ($userRepo->hasMacro('activeAdmins')) {
    echo "Macro exists\n";
}

// Remove a specific macro
$userRepo->removeMacro('activeAdmins');

// Clear all macros
$userRepo->clearMacros();

// Get all registered macros
$macros = $userRepo->getMacros();
foreach ($macros as $name => $closure) {
    echo "Registered macro: $name\n";
}

// Example 13: Macro with Joins
// ===========================
$userRepo->registerMacro('withRecentOrders', function(FluentQueryBuilder $query) {
    return $query
        ->leftJoin('orders', 'o', 'WITH', 'o.userId = u.id')
        ->where('o.createdAt', '>=', new DateTime('-30 days'))
        ->groupBy('u.id');
});

$activeUsersWithOrders = $userRepo->query()
    ->active()
    ->withRecentOrders()
    ->get();

// Example 14: Pagination with Macros
// ==================================
$userRepo->registerMacro('searchUsers', function(FluentQueryBuilder $query, $term) {
    return $query
        ->where('name', 'like', "%$term%")
        ->orWhere('email', 'like', "%$term%")
        ->orWhere('username', 'like', "%$term%");
});

// Use macro with pagination
$searchResults = $userRepo->query()
    ->searchUsers('john')
    ->active()
    ->paginate($request->get('page', 1), 20);

// Example 15: Testing with Macros
// ===============================
// In your test files, register test-specific macros
class UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepo->registerMacros([
            'testUsers' => fn($q) => $q->where('email', 'like', '%@test.com'),
            'excludeTestUsers' => fn($q) => $q->where('email', 'not like', '%@test.com')
        ]);
    }
    
    public function testActiveUsersExcludingTestAccounts()
    {
        $users = $this->userRepo->query()
            ->active()
            ->excludeTestUsers()
            ->get();
        
        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertStringNotContainsString('@test.com', $user->getEmail());
        }
    }
}