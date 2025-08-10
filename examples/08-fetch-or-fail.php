<?php

/**
 * Fetch or Fail Patterns Example
 * 
 * Demonstrates error handling patterns including:
 * - fetchOneOrFail() for throwing exceptions
 * - fetchOneOrCreate() for automatic entity creation
 * - updateOrCreate() for upsert operations
 * - sole() for ensuring exactly one result
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Exceptions\EntityNotFoundException;
use WelshDev\Doctrix\Exceptions\MultipleEntitiesFoundException;

// Example: Controller with Fetch or Fail
class UserController
{
    private UserRepository $userRepo;
    
    public function showUser(int $id)
    {
        // Option 1: Throw default EntityNotFoundException
        try {
            $user = $this->userRepo->fetchOneOrFail(['id' => $id]);
        } catch (EntityNotFoundException $e) {
            // Handle entity not found
            return response('User not found', 404);
        }
        
        // Option 2: Throw HTTP 404 exception directly (for Symfony controllers)
        $user = $this->userRepo->fetchOneOrFail(
            criteria: ['id' => $id],
            exception: new NotFoundHttpException('User not found')
        );
        
        // Option 3: Custom exception with message
        $user = $this->userRepo->fetchOneOrFail(
            criteria: ['uuid' => $uuid],
            exception: new \RuntimeException("User with UUID {$uuid} not found")
        );
        
        return view('user.show', ['user' => $user]);
    }
}

// Example: Fetch or Create Pattern
class RegistrationService
{
    private UserRepository $userRepo;
    
    public function registerUser(array $data)
    {
        // Create user if doesn't exist
        $user = $this->userRepo->fetchOneOrCreate(
            criteria: ['email' => $data['email']],
            defaults: [
                'name' => $data['name'],
                'status' => 'pending',
                'created_at' => new DateTime()
            ]
        );
        
        // Check if user was created or already existed
        if ($user->getId() === null) {
            echo "Creating new user\n";
            // Send welcome email
        } else {
            echo "User already exists\n";
            // Handle existing user
        }
        
        return $user;
    }
}

// Example: Update or Create Pattern
class LoginTracker
{
    private UserSessionRepository $sessionRepo;
    
    public function trackLogin(User $user, string $ipAddress)
    {
        // Update existing session or create new one
        $session = $this->sessionRepo->updateOrCreate(
            criteria: [
                'user' => $user,
                'ip_address' => $ipAddress
            ],
            values: [
                'last_activity' => new DateTime(),
                'login_count' => DB::raw('login_count + 1')
            ]
        );
        
        return $session;
    }
}

// Example: Sole Pattern - Ensure Exactly One Result
class SystemConfigService
{
    private ConfigRepository $configRepo;
    
    public function getMasterConfig()
    {
        try {
            // Ensure exactly one master config exists
            $config = $this->configRepo->sole(['type' => 'master']);
            return $config;
        } catch (EntityNotFoundException $e) {
            throw new \RuntimeException('No master configuration found');
        } catch (MultipleEntitiesFoundException $e) {
            throw new \RuntimeException('Multiple master configurations found - data integrity issue');
        }
    }
    
    public function getSuperAdmin()
    {
        // Alternative: Using sole() with custom exceptions
        return $this->userRepo->sole(
            criteria: ['role' => 'super_admin'],
            notFoundException: new \RuntimeException('No super admin exists'),
            multipleFoundException: new \RuntimeException('Multiple super admins found')
        );
    }
}

// Example: Chaining Fetch or Fail Operations
class OrderService
{
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private ProductRepository $productRepo;
    
    public function processOrder(int $orderId, int $userId)
    {
        // Chain multiple fetch or fail operations
        $order = $this->orderRepo->fetchOneOrFail(
            ['id' => $orderId, 'status' => 'pending'],
            new NotFoundHttpException('Pending order not found')
        );
        
        $user = $this->userRepo->fetchOneOrFail(
            ['id' => $userId, 'status' => 'active'],
            new NotFoundHttpException('Active user not found')
        );
        
        // Check product availability
        foreach ($order->getItems() as $item) {
            $product = $this->productRepo->fetchOneOrFail(
                ['id' => $item->getProductId(), 'in_stock' => true],
                new \RuntimeException("Product {$item->getProductId()} is out of stock")
            );
        }
        
        // Process the order...
        return $order;
    }
}

// Example: Repository Implementation
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    /**
     * Custom fetch or fail with business logic
     */
    public function findActiveUserOrFail(int $id): User
    {
        return $this->fetchOneOrFail(
            criteria: [
                'id' => $id,
                'status' => 'active',
                'deleted_at' => null
            ],
            exception: new NotFoundHttpException('Active user not found')
        );
    }
    
    /**
     * Find or create with additional logic
     */
    public function findOrCreateByEmail(string $email, array $userData = []): User
    {
        $user = $this->fetchOneOrCreate(
            criteria: ['email' => $email],
            defaults: array_merge([
                'status' => 'pending',
                'verification_token' => bin2hex(random_bytes(32)),
                'created_at' => new DateTime()
            ], $userData)
        );
        
        // Additional logic for new users
        if (!$user->getId()) {
            $this->sendVerificationEmail($user);
        }
        
        return $user;
    }
}

// Example: Error Handling Best Practices
class ApiController
{
    private UserRepository $userRepo;
    
    public function getUser(Request $request, int $id)
    {
        try {
            // Use fetchOneOrFail for clean error handling
            $user = $this->userRepo->fetchOneOrFail(['id' => $id]);
            
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (EntityNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }
    }
    
    public function ensureUniqueAdmin()
    {
        try {
            // Ensure exactly one admin exists
            $admin = $this->userRepo->sole(['role' => 'admin']);
            
            return response()->json([
                'success' => true,
                'admin' => $admin
            ]);
        } catch (EntityNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'No admin found'
            ], 404);
        } catch (MultipleEntitiesFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Multiple admins found',
                'count' => $e->getCount()
            ], 500);
        }
    }
}

// Usage Examples
echo "=== Fetch or Fail Patterns ===\n\n";

echo "1. Basic fetchOneOrFail:\n";
echo "   - Throws EntityNotFoundException by default\n";
echo "   - Can specify custom exception class\n";
echo "   - Perfect for controllers and APIs\n\n";

echo "2. fetchOneOrCreate:\n";
echo "   - Returns existing entity or creates new one\n";
echo "   - Useful for user registration, settings, etc.\n";
echo "   - Can provide default values for new entities\n\n";

echo "3. updateOrCreate:\n";
echo "   - Updates existing or creates new entity\n";
echo "   - Perfect for upsert operations\n";
echo "   - Commonly used for sessions, counters, etc.\n\n";

echo "4. sole():\n";
echo "   - Ensures exactly one result exists\n";
echo "   - Throws if zero or multiple found\n";
echo "   - Great for configuration, singleton entities\n\n";

echo "Best Practices:\n";
echo "- Use fetchOneOrFail in controllers for automatic 404s\n";
echo "- Use fetchOneOrCreate for idempotent operations\n";
echo "- Use sole() when data integrity requires exactly one result\n";
echo "- Always handle exceptions appropriately for your context\n";