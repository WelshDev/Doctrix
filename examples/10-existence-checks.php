<?php

/**
 * Existence Checks Example
 * 
 * Demonstrates various ways to check entity existence and counts:
 * - exists() and doesntExist() for boolean checks
 * - hasExactly(), hasAtLeast(), hasAtMost() for count validation
 * - hasBetween() for range checks
 * - isEmpty() and isNotEmpty() for collection checks
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WelshDev\Doctrix\BaseRepository;

// Example: User Registration Service
class RegistrationService
{
    private UserRepository $userRepo;
    
    /**
     * Check if email is available
     */
    public function isEmailAvailable(string $email): bool
    {
        return $this->userRepo->doesntExist(['email' => $email]);
    }
    
    /**
     * Check if username is taken
     */
    public function isUsernameTaken(string $username): bool
    {
        return $this->userRepo->exists(['username' => $username]);
    }
    
    /**
     * Validate registration data
     */
    public function validateRegistration(array $data): array
    {
        $errors = [];
        
        if ($this->userRepo->exists(['email' => $data['email']])) {
            $errors['email'] = 'Email already registered';
        }
        
        if ($this->userRepo->exists(['username' => $data['username']])) {
            $errors['username'] = 'Username already taken';
        }
        
        // Check if referral code exists
        if (!empty($data['referral_code'])) {
            if ($this->userRepo->doesntExist(['referral_code' => $data['referral_code']])) {
                $errors['referral_code'] = 'Invalid referral code';
            }
        }
        
        return $errors;
    }
}

// Example: Admin Validation Service
class AdminService
{
    private UserRepository $userRepo;
    private RoleRepository $roleRepo;
    
    /**
     * Ensure system has exactly one super admin
     */
    public function validateSuperAdmin(): bool
    {
        if (!$this->userRepo->hasExactly(1, ['role' => 'super_admin'])) {
            throw new \RuntimeException('System must have exactly one super admin');
        }
        return true;
    }
    
    /**
     * Check if we have minimum required admins
     */
    public function hasMinimumAdmins(): bool
    {
        return $this->userRepo->hasAtLeast(3, ['role' => 'admin', 'status' => 'active']);
    }
    
    /**
     * Ensure not too many pending approvals
     */
    public function canCreatePendingUser(): bool
    {
        // Maximum 50 pending users allowed
        return $this->userRepo->hasAtMost(50, ['status' => 'pending']);
    }
    
    /**
     * Check if admin count is within acceptable range
     */
    public function isAdminCountValid(): bool
    {
        // Between 3 and 10 admins
        return $this->userRepo->hasBetween(3, 10, ['role' => 'admin']);
    }
}

// Example: Inventory Management
class InventoryService
{
    private ProductRepository $productRepo;
    private WarehouseRepository $warehouseRepo;
    
    /**
     * Check if product exists in any warehouse
     */
    public function isProductAvailable(int $productId): bool
    {
        return $this->warehouseRepo->exists([
            'product_id' => $productId,
            ['quantity', 'gt', 0]
        ]);
    }
    
    /**
     * Check if warehouse is empty
     */
    public function isWarehouseEmpty(int $warehouseId): bool
    {
        return $this->productRepo->isEmpty([
            'warehouse_id' => $warehouseId,
            ['quantity', 'gt', 0]
        ]);
    }
    
    /**
     * Check if we have low stock items
     */
    public function hasLowStockItems(): bool
    {
        return $this->productRepo->isNotEmpty([
            ['quantity', 'lte', 10],
            ['quantity', 'gt', 0]
        ]);
    }
    
    /**
     * Validate warehouse capacity
     */
    public function canAddToWarehouse(int $warehouseId): bool
    {
        // Each warehouse can hold maximum 1000 different products
        return $this->productRepo->hasAtMost(1000, ['warehouse_id' => $warehouseId]);
    }
}

// Example: Order Validation
class OrderService
{
    private OrderRepository $orderRepo;
    private OrderItemRepository $itemRepo;
    
    /**
     * Check if user has any orders
     */
    public function hasUserOrdered(int $userId): bool
    {
        return $this->orderRepo->exists(['user_id' => $userId]);
    }
    
    /**
     * Check if user has pending orders
     */
    public function hasPendingOrders(int $userId): bool
    {
        return $this->orderRepo->exists([
            'user_id' => $userId,
            'status' => 'pending'
        ]);
    }
    
    /**
     * Validate order limits
     */
    public function canPlaceOrder(int $userId): array
    {
        $validation = [
            'can_order' => true,
            'reasons' => []
        ];
        
        // Check if user has too many pending orders
        if ($this->orderRepo->hasAtLeast(5, [
            'user_id' => $userId,
            'status' => 'pending'
        ])) {
            $validation['can_order'] = false;
            $validation['reasons'][] = 'Too many pending orders';
        }
        
        // Check if user has unpaid orders
        if ($this->orderRepo->exists([
            'user_id' => $userId,
            'payment_status' => 'unpaid',
            ['created_at', 'lt', new DateTime('-7 days')]
        ])) {
            $validation['can_order'] = false;
            $validation['reasons'][] = 'Unpaid orders exist';
        }
        
        return $validation;
    }
}

// Example: Content Moderation
class ModerationService
{
    private PostRepository $postRepo;
    private CommentRepository $commentRepo;
    private ReportRepository $reportRepo;
    
    /**
     * Check if content needs moderation
     */
    public function needsModeration(): bool
    {
        // Check if there are unmoderated posts
        if ($this->postRepo->exists(['status' => 'pending_review'])) {
            return true;
        }
        
        // Check if there are reported comments
        if ($this->reportRepo->exists(['type' => 'comment', 'resolved' => false])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get moderation statistics
     */
    public function getModerationStats(): array
    {
        return [
            'has_pending_posts' => $this->postRepo->isNotEmpty(['status' => 'pending_review']),
            'has_reported_content' => $this->reportRepo->isNotEmpty(['resolved' => false]),
            'needs_urgent_attention' => $this->reportRepo->hasAtLeast(10, [
                'resolved' => false,
                ['created_at', 'lt', new DateTime('-24 hours')]
            ]),
            'spam_threshold_reached' => $this->postRepo->hasAtLeast(50, [
                'status' => 'spam',
                ['created_at', 'gte', new DateTime('-1 hour')]
            ])
        ];
    }
}

// Example: Repository Implementation
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    /**
     * Check if email is verified
     */
    public function isEmailVerified(string $email): bool
    {
        return $this->exists([
            'email' => $email,
            'email_verified' => true
        ]);
    }
    
    /**
     * Check if user can be deleted
     */
    public function canDeleteUser(int $userId): bool
    {
        // Can't delete if user has active orders
        $orderRepo = $this->em->getRepository(Order::class);
        if ($orderRepo->exists([
            'user_id' => $userId,
            ['status', 'in', ['pending', 'processing']]
        ])) {
            return false;
        }
        
        // Can't delete if user is the only admin
        if ($this->hasExactly(1, ['role' => 'admin']) && 
            $this->exists(['id' => $userId, 'role' => 'admin'])) {
            return false;
        }
        
        return true;
    }
}

// Example: Combined Existence Checks
class SystemHealthService
{
    private UserRepository $userRepo;
    private ConfigRepository $configRepo;
    private LogRepository $logRepo;
    
    /**
     * Perform system health checks
     */
    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];
        
        // Check admin exists
        $health['checks']['has_admin'] = $this->userRepo->exists(['role' => 'admin']);
        if (!$health['checks']['has_admin']) {
            $health['status'] = 'critical';
        }
        
        // Check configuration exists
        $health['checks']['has_config'] = $this->configRepo->isNotEmpty();
        if (!$health['checks']['has_config']) {
            $health['status'] = 'critical';
        }
        
        // Check for recent errors
        $health['checks']['recent_errors'] = $this->logRepo->hasAtMost(10, [
            'level' => 'error',
            ['created_at', 'gte', new DateTime('-1 hour')]
        ]);
        if (!$health['checks']['recent_errors']) {
            $health['status'] = 'warning';
        }
        
        // Check for critical errors
        $health['checks']['no_critical_errors'] = $this->logRepo->doesntExist([
            'level' => 'critical',
            ['created_at', 'gte', new DateTime('-24 hours')]
        ]);
        if (!$health['checks']['no_critical_errors']) {
            $health['status'] = 'critical';
        }
        
        return $health;
    }
}

// Usage Examples
echo "=== Existence Check Examples ===\n\n";

echo "1. exists() - Check if any matching entities exist:\n";
echo "   if (\$repo->exists(['status' => 'active'])) { ... }\n\n";

echo "2. doesntExist() - Check if no matching entities exist:\n";
echo "   if (\$repo->doesntExist(['email' => \$email])) { ... }\n\n";

echo "3. hasExactly() - Check for exact count:\n";
echo "   if (\$repo->hasExactly(1, ['role' => 'super_admin'])) { ... }\n\n";

echo "4. hasAtLeast() - Check for minimum count:\n";
echo "   if (\$repo->hasAtLeast(3, ['status' => 'active'])) { ... }\n\n";

echo "5. hasAtMost() - Check for maximum count:\n";
echo "   if (\$repo->hasAtMost(50, ['status' => 'pending'])) { ... }\n\n";

echo "6. hasBetween() - Check if count is in range:\n";
echo "   if (\$repo->hasBetween(5, 10, ['type' => 'premium'])) { ... }\n\n";

echo "7. isEmpty() - Check if criteria returns no results:\n";
echo "   if (\$repo->isEmpty(['status' => 'deleted'])) { ... }\n\n";

echo "8. isNotEmpty() - Check if criteria returns any results:\n";
echo "   if (\$repo->isNotEmpty(['unread' => true])) { ... }\n\n";

echo "Best Practices:\n";
echo "- Use exists() instead of count() > 0 for better performance\n";
echo "- Use specific count methods for validation logic\n";
echo "- Combine checks for complex validation scenarios\n";
echo "- These methods use COUNT queries, not fetching entities\n";