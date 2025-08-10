<?php
/**
 * Bulk Operations Examples
 * 
 * This file demonstrates how to use Doctrix's bulk update and delete features
 * for efficient database operations without fetching entities.
 */

use App\Repository\UserRepository;
use App\Repository\LogRepository;
use App\Repository\SessionRepository;
use App\Repository\NotificationRepository;

// Example 1: Basic Bulk Update
// ============================
// Deactivate all users who haven't logged in for 6 months
$affectedRows = $userRepo->bulkUpdate(
    ['status' => 'inactive', 'updatedAt' => new DateTime()],
    [
        'status' => 'active',
        ['lastLogin', 'lt', new DateTime('-6 months')]
    ]
);
echo "Deactivated $affectedRows users\n";

// Example 2: Bulk Update with Complex Criteria
// ============================================
// Archive completed projects older than 1 year
$projectRepo->bulkUpdate(
    [
        'status' => 'archived',
        'archivedAt' => new DateTime(),
        'archivedBy' => $currentUser->getId()
    ],
    [
        'status' => 'completed',
        ['completedAt', 'lte', new DateTime('-1 year')],
        'archived' => false
    ]
);

// Example 3: Basic Bulk Delete
// ============================
// Delete all expired sessions
$deleted = $sessionRepo->bulkDelete([
    ['expiresAt', 'lt', new DateTime()]
]);
echo "Deleted $deleted expired sessions\n";

// Example 4: Bulk Delete with Multiple Criteria
// =============================================
// Delete old debug logs
$logRepo->bulkDelete([
    'level' => 'debug',
    ['createdAt', 'lt', new DateTime('-30 days')]
]);

// Example 5: Bulk Operations with Limit
// =====================================
// Delete only the oldest 1000 logs (useful for batch processing)
$logRepo->bulkDelete(
    ['level' => 'info'],
    ['createdAt' => 'ASC'],  // Order by oldest first
    1000                      // Limit to 1000 records
);

// Example 6: Conditional Bulk Update
// ==================================
// Only update if fewer than 100 records would be affected
$notificationRepo->conditionalBulkUpdate(
    ['status' => 'archived'],
    ['status' => 'read', ['createdAt', 'lt', new DateTime('-90 days')]],
    function($count) {
        return $count < 100; // Only proceed if less than 100 records
    }
);

// Example 7: Safe Bulk Delete with Dry Run
// ========================================
// First, check what would be deleted (dry run)
$dryRun = $userRepo->safeBulkDelete(
    ['status' => 'deleted', ['deletedAt', 'lt', new DateTime('-1 year')]],
    true  // Dry run mode
);
echo "Would delete {$dryRun['count']} users\n";

// If okay, actually delete
if ($dryRun['count'] < 10000) {
    $actual = $userRepo->safeBulkDelete(
        ['status' => 'deleted', ['deletedAt', 'lt', new DateTime('-1 year')]],
        false  // Actually delete
    );
    echo "Deleted {$actual['deleted']} users\n";
}

// Example 8: Batch Processing for Large Datasets
// ==============================================
// Process large dataset in batches to avoid memory issues
$totalUpdated = $orderRepo->bulkBatch(
    'update',
    ['status' => 'pending', ['createdAt', 'lt', new DateTime('-1 hour')]],
    ['status' => 'processing', 'processedAt' => new DateTime()],
    500  // Process in batches of 500
);
echo "Updated $totalUpdated orders in batches\n";

// Example 9: Count Before Operation
// =================================
// Check how many records would be affected before proceeding
$count = $commentRepo->countMatching([
    'status' => 'spam',
    ['score', 'gte', 0.9]
]);

if ($count > 0) {
    echo "Found $count spam comments\n";
    
    if ($count < 1000) {
        // Delete all at once if reasonable number
        $commentRepo->bulkDelete([
            'status' => 'spam',
            ['score', 'gte', 0.9]
        ]);
    } else {
        // Process in batches for large numbers
        $commentRepo->bulkBatch('delete', [
            'status' => 'spam',
            ['score', 'gte', 0.9]
        ], [], 100);
    }
}

// Example 10: Combining with OR Conditions
// ========================================
// Update users matching any of the conditions
$userRepo->bulkUpdate(
    ['requiresReview' => true, 'reviewReason' => 'suspicious_activity'],
    [
        ['or', [
            ['loginAttempts', 'gte', 5],
            ['lastIpAddress', 'in', $suspiciousIps],
            ['email', 'like', '%@suspicious-domain.com']
        ]]
    ]
);

// Example 11: Bulk Update with NULL Values
// ========================================
// Clear sensitive data for deleted accounts
$userRepo->bulkUpdate(
    [
        'email' => null,
        'phone' => null,
        'address' => null,
        'personalData' => null
    ],
    [
        'status' => 'deleted',
        ['deletedAt', 'lt', new DateTime('-30 days')]
    ]
);

// Example 12: Complex Update with Multiple Field Types
// ====================================================
$productRepo->bulkUpdate(
    [
        'onSale' => true,
        'salePrice' => 19.99,
        'saleStartDate' => new DateTime(),
        'saleEndDate' => new DateTime('+7 days'),
        'updatedBy' => $currentUser->getId()
    ],
    [
        'category' => 'electronics',
        ['stock', 'gte', 10],
        ['price', 'between', 20, 100],
        'discontinued' => false
    ]
);

// Example 13: Repository Integration
// ==================================
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    /**
     * Deactivate inactive users
     */
    public function deactivateInactiveUsers(int $months = 6): int
    {
        return $this->bulkUpdate(
            [
                'status' => 'inactive',
                'deactivatedAt' => new DateTime()
            ],
            [
                'status' => 'active',
                ['lastLogin', 'lt', new DateTime("-$months months")]
            ]
        );
    }
    
    /**
     * Clean up old deleted users
     */
    public function purgeOldDeletedUsers(): int
    {
        return $this->bulkDelete([
            'status' => 'deleted',
            ['deletedAt', 'lt', new DateTime('-1 year')]
        ]);
    }
    
    /**
     * Archive users with suspicious activity
     */
    public function archiveSuspiciousUsers(): int
    {
        return $this->conditionalBulkUpdate(
            ['status' => 'suspended', 'suspendedAt' => new DateTime()],
            [
                ['or', [
                    ['loginAttempts', 'gte', 10],
                    ['flaggedCount', 'gte', 3]
                ]]
            ],
            fn($count) => $count < 50  // Only if less than 50 users
        );
    }
}

// Example 14: Transactional Bulk Operations
// =========================================
$em->beginTransaction();
try {
    // Update related records
    $orderRepo->bulkUpdate(
        ['status' => 'cancelled'],
        ['customerId' => $customer->getId(), 'status' => 'pending']
    );
    
    // Update customer status
    $customerRepo->bulkUpdate(
        ['status' => 'inactive'],
        ['id' => $customer->getId()]
    );
    
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}

// Example 15: Performance Monitoring
// ==================================
$startTime = microtime(true);
$startMemory = memory_get_usage();

// Perform bulk operation
$affected = $logRepo->bulkDelete([
    'level' => 'debug',
    ['createdAt', 'lt', new DateTime('-7 days')]
]);

$executionTime = microtime(true) - $startTime;
$memoryUsed = memory_get_usage() - $startMemory;

echo sprintf(
    "Deleted %d logs in %.2f seconds using %.2f MB memory\n",
    $affected,
    $executionTime,
    $memoryUsed / 1024 / 1024
);