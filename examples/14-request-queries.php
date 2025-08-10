<?php
/**
 * Request-Based Query Examples
 * 
 * This file demonstrates how to build secure, validated queries from HTTP requests
 * using Doctrix's request query features.
 */

use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use WelshDev\Doctrix\Request\RequestQuerySchema;
use Symfony\Component\HttpFoundation\Request;

// Example 1: Simple Request Query
// ================================
// Repository with predefined schema
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    protected function defineRequestSchema(): RequestQuerySchema
    {
        return RequestQuerySchema::preset('basic')
            ->searchable(['name', 'email', 'status', 'role'])
            ->sortable(['createdAt', 'name', 'lastLogin'])
            ->defaults(['status' => 'active'])
            ->maxLimit(100);
    }
}

// In controller:
public function index(Request $request, UserRepository $repo)
{
    // Automatically builds query from request parameters
    // GET /users?status=active&role=admin&sort=-createdAt&page=1&limit=20
    $users = $repo->fromRequest($request)->get();
    
    // Or with pagination
    $paginated = $repo->paginateFromRequest($request);
    
    return $this->render('users/index.html.twig', [
        'users' => $paginated
    ]);
}

// Example 2: Schema with Field Configuration
// ==========================================
class ProductRepository extends BaseRepository
{
    protected function defineRequestSchema(): RequestQuerySchema
    {
        $schema = new RequestQuerySchema();
        
        // Configure individual fields with validation
        $schema->field('name')
            ->searchable(['eq', 'like'])
            ->sortable();
        
        $schema->field('price')
            ->searchable(['eq', 'gte', 'lte', 'between'])
            ->sortable()
            ->numeric(0, 999999);
        
        $schema->field('category')
            ->searchable()
            ->enum(['electronics', 'clothing', 'books', 'food']);
        
        $schema->field('inStock')
            ->searchable()
            ->boolean();
        
        $schema->field('createdAt')
            ->searchable(['gte', 'lte', 'between'])
            ->sortable()
            ->date('Y-m-d');
        
        // Configure global settings
        $schema->maxLimit(50)
            ->defaultLimit(20)
            ->strictMode(true);
        
        return $schema;
    }
}

// GET /products?category=electronics&price_gte=100&price_lte=500&inStock=true&sort=price
$products = $productRepo->fromRequest($request)->get();

// Example 3: Custom Schema per Action
// ===================================
public function search(Request $request, ProductRepository $repo)
{
    // Create custom schema for this specific action
    $schema = RequestQuerySchema::preset('api')
        ->searchable([
            'name', 'description', 'sku', 'category'
        ])
        ->sortable(['price', 'popularity', 'createdAt'])
        ->field('price')->numeric(0, 10000)
        ->field('rating')->numeric(1, 5)
        ->require('category'); // Category is required
    
    try {
        $products = $repo->fromRequest($request, $schema)->get();
    } catch (RequestQueryException $e) {
        return $this->json([
            'error' => 'Invalid query parameters',
            'details' => $e->getErrors()
        ], 400);
    }
    
    return $this->json($products);
}

// Example 4: Request Query with Relations
// =======================================
class OrderRepository extends BaseRepository
{
    protected function defineRequestSchema(): RequestQuerySchema
    {
        return RequestQuerySchema::preset('basic')
            ->searchable(['status', 'orderNumber', 'total'])
            ->sortable(['createdAt', 'total'])
            // Allow filtering on related entities
            ->allowRelations([
                'customer' => ['name', 'email', 'country'],
                'items' => ['productId', 'quantity']
            ]);
    }
}

// GET /orders?customer.country=US&customer.email=john@example.com&items.quantity_gte=5
$orders = $orderRepo->fromRequest($request)->get();

// Example 5: Different Parameter Styles
// =====================================
// Standard style (default)
// GET /users?status=active&role=admin&created_at_gte=2024-01-01

// JSON:API style
$schema = RequestQuerySchema::preset('api')
    ->configure(['parameterStyle' => 'jsonapi']);
// GET /users?filter[status]=active&filter[role]=admin&filter[createdAt][gte]=2024-01-01

// GraphQL-like style
$schema = RequestQuerySchema::preset('api')
    ->configure(['parameterStyle' => 'graphql']);
// POST /users/search
// {
//   "where": {
//     "status": "active",
//     "role": "admin",
//     "createdAt": { "gte": "2024-01-01" }
//   },
//   "orderBy": [{ "field": "createdAt", "direction": "DESC" }],
//   "limit": 20
// }

// Example 6: Request Query Helper for Advanced Control
// ====================================================
public function advancedSearch(Request $request, UserRepository $repo)
{
    $results = $repo->requestQuery()
        ->allowFields(['name', 'email', 'status', 'country'])
        ->allowSorting(['createdAt', 'name'])
        ->withDefaults(['status' => 'active'])
        ->override(['verified' => true]) // Force this filter
        ->validate(false) // Disable strict validation
        ->cache('user_search_' . md5($request->getQueryString()), 3600)
        ->fromRequest($request)
        ->get();
    
    return $this->json($results);
}

// Example 7: Field Aliases and Transformations
// ============================================
$schema = new RequestQuerySchema();

// Map frontend parameter names to entity fields
$schema->aliases([
    'user' => 'userId',           // ?user=123 maps to userId field
    'dateFrom' => 'createdAt_gte', // ?dateFrom=2024-01-01
    'dateTo' => 'createdAt_lte',   // ?dateTo=2024-12-31
    'q' => 'search'                // ?q=term for search
]);

// Transform values before applying to query
$schema->field('status')
    ->searchable()
    ->transform(function($value) {
        // Map frontend values to database values
        $statusMap = [
            'published' => 1,
            'draft' => 0,
            'archived' => 2
        ];
        return $statusMap[$value] ?? $value;
    });

// Example 8: Validation and Error Handling
// ========================================
public function searchWithValidation(Request $request, ProductRepository $repo)
{
    $schema = RequestQuerySchema::preset('strict')
        ->searchable(['name', 'category', 'price'])
        ->field('price')
            ->numeric(0, 10000)
            ->validator(function($value) {
                if ($value < 0) {
                    return 'Price cannot be negative';
                }
                return true;
            })
        ->field('category')
            ->enum(['electronics', 'books', 'clothing'])
            ->required();
    
    // Validate before building query
    $errors = $schema->validate($request->query->all());
    if (!empty($errors)) {
        return $this->json(['errors' => $errors], 400);
    }
    
    $products = $repo->fromRequest($request, $schema)->get();
    return $this->json($products);
}

// Example 9: Combining Manual and Request Queries
// ===============================================
public function complexSearch(Request $request, UserRepository $repo)
{
    // Start with request-based query
    $query = $repo->fromRequest($request);
    
    // Add additional manual conditions
    if ($this->isGranted('ROLE_ADMIN')) {
        // Admins see all users
    } else {
        // Regular users only see active users
        $query->where('status', 'active')
              ->where('verified', true);
    }
    
    // Add complex conditions that can't come from request
    $query->where(function($qb) {
        $qb->where('lastLogin', '>=', new DateTime('-30 days'))
           ->orWhere('isVip', true);
    });
    
    return $query->paginate();
}

// Example 10: API Endpoint with Full Features
// ===========================================
/**
 * @Route("/api/products", methods={"GET", "POST"})
 */
public function apiSearch(Request $request, ProductRepository $repo)
{
    // Configure schema for API
    $schema = RequestQuerySchema::preset('api')
        ->searchable([
            'name', 'description', 'sku', 'barcode',
            'category', 'subcategory', 'brand',
            'price', 'salePrice', 'cost',
            'stock', 'weight', 'status'
        ])
        ->sortable([
            'name', 'price', 'stock', 'createdAt', 
            'popularity', 'rating', 'salesCount'
        ])
        ->field('price')->numeric(0, 999999)
        ->field('salePrice')->numeric(0, 999999)
        ->field('stock')->numeric(0, null)
        ->field('weight')->numeric(0, null)
        ->field('status')->enum(['active', 'inactive', 'discontinued'])
        ->field('createdAt')->date('Y-m-d')
        ->field('rating')->numeric(0, 5)
        ->maxLimit(100)
        ->defaultLimit(20);
    
    try {
        // Support both GET and POST
        if ($request->getMethod() === 'POST') {
            $params = json_decode($request->getContent(), true);
        } else {
            $params = $request->query->all();
        }
        
        $results = $repo->paginateFromRequest($params, $schema);
        
        return $this->json([
            'data' => $results->items,
            'meta' => [
                'total' => $results->total,
                'page' => $results->currentPage,
                'perPage' => $results->perPage,
                'lastPage' => $results->lastPage,
                'hasMore' => $results->hasMore
            ],
            'filters' => $params
        ]);
        
    } catch (RequestQueryException $e) {
        return $this->json([
            'error' => 'Invalid query parameters',
            'details' => $e->getErrors()
        ], 400);
    }
}

// Example 11: Search with Text Search
// ===================================
// GET /products?search=laptop&category=electronics&price_lte=2000
$schema = new RequestQuerySchema();
$schema->searchable(['name', 'description', 'tags'])
    ->field('search')
        ->transform(function($term) use ($schema) {
            // Will search in name, description, and tags fields
            return ['like', "%$term%"];
        });

// Example 12: Date Range Handling
// ===============================
// GET /orders?dateFrom=2024-01-01&dateTo=2024-12-31&status=completed
$schema = new RequestQuerySchema();
$schema->field('dateFrom')
    ->searchable(['gte'])
    ->date('Y-m-d')
    ->transform(fn($date) => ['createdAt', 'gte', $date]);

$schema->field('dateTo')
    ->searchable(['lte'])
    ->date('Y-m-d')
    ->transform(fn($date) => ['createdAt', 'lte', $date]);

// Example 13: Dynamic Schema Based on User Role
// ==============================================
public function search(Request $request, UserRepository $repo)
{
    $schema = new RequestQuerySchema();
    
    // Basic fields for all users
    $schema->searchable(['name', 'status'])
           ->sortable(['name', 'createdAt']);
    
    // Add more fields based on role
    if ($this->isGranted('ROLE_ADMIN')) {
        $schema->searchable(['email', 'role', 'lastLogin', 'ipAddress'])
               ->sortable(['lastLogin', 'role']);
    }
    
    if ($this->isGranted('ROLE_SUPER_ADMIN')) {
        $schema->searchable(['deleted', 'locked', 'failedAttempts'])
               ->allowRelations(['profile' => ['*']]);
    }
    
    return $repo->fromRequest($request, $schema)->get();
}

// Example 14: Preset Schemas
// ==========================
// Basic preset - lenient validation, reasonable limits
$basicSchema = RequestQuerySchema::preset('basic');

// Strict preset - strict validation, lower limits, pagination required
$strictSchema = RequestQuerySchema::preset('strict');

// API preset - JSON:API style, strict validation
$apiSchema = RequestQuerySchema::preset('api');

// Admin preset - high limits, deep relations allowed
$adminSchema = RequestQuerySchema::preset('admin');

// Example 15: Testing Request Queries
// ===================================
class ProductControllerTest extends WebTestCase
{
    public function testSearchProducts()
    {
        $client = static::createClient();
        
        // Test basic search
        $client->request('GET', '/products', [
            'category' => 'electronics',
            'price_gte' => 100,
            'price_lte' => 500,
            'sort' => '-price',
            'page' => 1,
            'limit' => 10
        ]);
        
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $data['data']);
        
        // Test validation
        $client->request('GET', '/products', [
            'category' => 'invalid_category',
            'price' => 'not_a_number'
        ]);
        
        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }
}