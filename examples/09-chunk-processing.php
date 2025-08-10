<?php

/**
 * Chunk Processing Example
 * 
 * Demonstrates memory-efficient processing of large datasets:
 * - chunk() for batch processing
 * - each() for individual processing
 * - lazy() for generator-based iteration
 * - batchProcess() for transactional batches
 * - map() for transformations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WelshDev\Doctrix\BaseRepository;

// Example: Newsletter Service using Chunks
class NewsletterService
{
    private UserRepository $userRepo;
    private EmailService $emailService;
    
    /**
     * Send newsletter to all active users in chunks
     */
    public function sendNewsletterToAll(): array
    {
        $stats = ['sent' => 0, 'failed' => 0];
        
        // Process 500 users at a time to avoid memory issues
        $this->userRepo->chunk(500, function($users) use (&$stats) {
            foreach ($users as $user) {
                try {
                    $this->emailService->send($user->getEmail(), 'newsletter');
                    $stats['sent']++;
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->logError($e, $user);
                }
            }
            
            // Clear entity manager to free memory
            $this->entityManager->clear();
        });
        
        return $stats;
    }
    
    /**
     * Send with progress tracking
     */
    public function sendWithProgress(): void
    {
        $this->userRepo->chunkWithProgress(
            chunkSize: 100,
            callback: function($batch) {
                foreach ($batch as $user) {
                    $this->emailService->send($user->getEmail(), 'newsletter');
                }
            },
            progressCallback: function($processed, $total) {
                $percentage = round(($processed / $total) * 100, 2);
                echo "\rProgress: {$processed}/{$total} ({$percentage}%)";
                
                // Update progress in database or cache
                Cache::put('newsletter_progress', [
                    'processed' => $processed,
                    'total' => $total,
                    'percentage' => $percentage
                ]);
            }
        );
        echo "\nNewsletter sent successfully!\n";
    }
}

// Example: Data Migration using Each
class DataMigrationService
{
    private UserRepository $userRepo;
    
    /**
     * Migrate user data one by one
     */
    public function migrateUserData(): void
    {
        echo "Starting user data migration...\n";
        
        // Process each user individually with index
        $this->userRepo->each(function($user, $index) {
            // Show progress every 100 users
            if ($index % 100 === 0) {
                echo "Processing user #{$index}\n";
            }
            
            // Perform migration
            $this->migrateUser($user);
            
            // Free memory periodically
            if ($index % 500 === 0) {
                $this->entityManager->clear();
            }
        }, chunkSize: 200); // Fetch 200 at a time
        
        echo "Migration completed!\n";
    }
    
    private function migrateUser($user): void
    {
        // Migration logic
        $user->setMigratedAt(new DateTime());
        $user->setLegacyId($user->getId());
        // ... more migration logic
    }
}

// Example: Lazy Loading for Export
class ExportService
{
    private ProductRepository $productRepo;
    
    /**
     * Export products to CSV using lazy loading
     */
    public function exportToCsv(string $filename): void
    {
        $file = fopen($filename, 'w');
        
        // Write header
        fputcsv($file, ['ID', 'Name', 'Price', 'Stock', 'Category']);
        
        // Use lazy loading to process one product at a time
        // But fetch 100 at a time for efficiency
        foreach ($this->productRepo->lazy(100) as $product) {
            fputcsv($file, [
                $product->getId(),
                $product->getName(),
                $product->getPrice(),
                $product->getStock(),
                $product->getCategory()->getName()
            ]);
        }
        
        fclose($file);
        echo "Export completed: {$filename}\n";
    }
    
    /**
     * Stream JSON response for API
     */
    public function streamJsonResponse(): void
    {
        header('Content-Type: application/json');
        echo '{"products":[';
        
        $first = true;
        foreach ($this->productRepo->lazy(50) as $product) {
            if (!$first) echo ',';
            echo json_encode([
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice()
            ]);
            $first = false;
            
            // Flush output to send data immediately
            ob_flush();
            flush();
        }
        
        echo ']}';
    }
}

// Example: Batch Processing with Transactions
class OrderProcessingService
{
    private OrderRepository $orderRepo;
    
    /**
     * Process pending orders in transactional batches
     */
    public function processPendingOrders(): array
    {
        $results = $this->orderRepo->batchProcess(
            chunkSize: 50,
            callback: function($orders) {
                foreach ($orders as $order) {
                    // Process order
                    $this->processOrder($order);
                    $order->setStatus('processed');
                    $order->setProcessedAt(new DateTime());
                }
                // Each batch is wrapped in a transaction
                // If any order fails, entire batch is rolled back
            },
            criteria: ['status' => 'pending']
        );
        
        echo "Processed: {$results['success']} batches\n";
        echo "Failed: {$results['failed']} batches\n";
        echo "Total entities: {$results['total_entities']}\n";
        
        return $results;
    }
}

// Example: Map Transformation
class ReportService
{
    private UserRepository $userRepo;
    private OrderRepository $orderRepo;
    
    /**
     * Get all user emails efficiently
     */
    public function getAllEmails(): array
    {
        // Map over all users, extracting just emails
        return $this->userRepo->map(fn($user) => $user->getEmail());
    }
    
    /**
     * Generate revenue report
     */
    public function generateRevenueReport(): array
    {
        // Map with chunk size for memory efficiency
        $revenues = $this->orderRepo->map(
            callback: fn($order) => [
                'order_id' => $order->getId(),
                'date' => $order->getCreatedAt()->format('Y-m-d'),
                'total' => $order->getTotal(),
                'status' => $order->getStatus()
            ],
            chunkSize: 1000,
            criteria: ['status' => 'completed']
        );
        
        return $revenues;
    }
}

// Example: Complex Chunk Processing
class UserCleanupService
{
    private UserRepository $userRepo;
    
    /**
     * Clean up inactive users with multiple operations
     */
    public function cleanupInactiveUsers(): void
    {
        $criteria = [
            ['last_login', 'lt', new DateTime('-1 year')],
            'status' => 'inactive'
        ];
        
        // First pass: Send warning emails
        $this->userRepo->chunk(200, function($users) {
            foreach ($users as $user) {
                $this->sendWarningEmail($user);
                $user->setWarningEmailSent(true);
                $user->setWarningEmailDate(new DateTime());
            }
        }, $criteria);
        
        // Second pass: Archive old data
        $archiveCriteria = array_merge($criteria, [
            'warning_email_sent' => true,
            ['warning_email_date', 'lt', new DateTime('-30 days')]
        ]);
        
        $this->userRepo->chunk(100, function($users) {
            foreach ($users as $user) {
                $this->archiveUserData($user);
                $user->setArchived(true);
            }
        }, $archiveCriteria);
    }
}

// Example: Memory-Efficient Aggregation
class StatisticsService
{
    private TransactionRepository $transactionRepo;
    
    /**
     * Calculate statistics without loading all data
     */
    public function calculateMonthlyStats(): array
    {
        $stats = [
            'total' => 0,
            'count' => 0,
            'by_category' => []
        ];
        
        // Process in chunks to avoid memory issues
        $this->transactionRepo->chunk(1000, function($transactions) use (&$stats) {
            foreach ($transactions as $transaction) {
                $stats['total'] += $transaction->getAmount();
                $stats['count']++;
                
                $category = $transaction->getCategory();
                if (!isset($stats['by_category'][$category])) {
                    $stats['by_category'][$category] = 0;
                }
                $stats['by_category'][$category] += $transaction->getAmount();
            }
        }, [
            ['created_at', 'gte', new DateTime('first day of this month')],
            ['created_at', 'lt', new DateTime('first day of next month')]
        ]);
        
        $stats['average'] = $stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0;
        
        return $stats;
    }
}

// Usage Examples
echo "=== Chunk Processing Examples ===\n\n";

echo "1. chunk() - Process in batches:\n";
echo "   - Fetches data in chunks to save memory\n";
echo "   - Ideal for bulk operations\n";
echo "   - Can clear entity manager between chunks\n\n";

echo "2. each() - Process individually:\n";
echo "   - Processes one entity at a time\n";
echo "   - Still fetches in chunks for efficiency\n";
echo "   - Provides index for progress tracking\n\n";

echo "3. lazy() - Generator-based iteration:\n";
echo "   - Returns a generator for memory efficiency\n";
echo "   - Perfect for exports and streaming\n";
echo "   - Fetches data as needed\n\n";

echo "4. batchProcess() - Transactional batches:\n";
echo "   - Wraps each chunk in a transaction\n";
echo "   - Provides success/failure statistics\n";
echo "   - Rollback on batch failure\n\n";

echo "5. map() - Transform entities:\n";
echo "   - Applies transformation to all entities\n";
echo "   - Memory efficient with chunking\n";
echo "   - Returns array of transformed values\n\n";

echo "Best Practices:\n";
echo "- Use appropriate chunk size (100-1000 typically)\n";
echo "- Clear entity manager periodically for long operations\n";
echo "- Use lazy() for exports and streaming\n";
echo "- Monitor memory usage with memory_get_usage()\n";
echo "- Add progress tracking for user feedback\n";