<?php
/**
 * Basic Usage Examples for Doctrix
 * 
 * This file demonstrates the fundamental features of Doctrix
 */

use WelshDev\Doctrix\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;

// Example 1: Simple Repository Setup
// ===================================

class UserRepository extends BaseRepository
{
    // Define the alias for your entity (used in queries)
    protected string $alias = 'u';
    
    // Optional: Define joins that should always be applied
    protected array $joins = [
        ['leftJoin', 'u.profile', 'p'],
        ['leftJoin', 'u.company', 'c'],
    ];
}

// Example 2: Basic Fetch Operations
// ==================================

class UserController
{
    private UserRepository $userRepo;
    
    public function basicQueries(): void
    {
        // Fetch all users
        $allUsers = $this->userRepo->fetchAll();
        
        // Fetch with simple criteria
        $activeUsers = $this->userRepo->fetch(['status' => 'active']);
        
        // Fetch with multiple criteria (AND condition)
        $verifiedAdmins = $this->userRepo->fetch([
            'status' => 'active',
            'role' => 'admin',
            'emailVerified' => true
        ]);
        
        // Fetch with ordering
        $sortedUsers = $this->userRepo->fetch(
            criteria: ['status' => 'active'],
            orderBy: ['created' => 'DESC', 'name' => 'ASC']
        );
        
        // Fetch with limit and offset
        $pagedUsers = $this->userRepo->fetch(
            criteria: ['status' => 'active'],
            orderBy: ['created' => 'DESC'],
            limit: 10,
            offset: 20
        );
        
        // Fetch single entity
        $user = $this->userRepo->fetchOne(['email' => 'john@example.com']);
        
        // Count entities
        $totalUsers = $this->userRepo->count(['status' => 'active']);
    }
}

// Example 3: Using Operators
// ===========================

class OperatorExamples
{
    private UserRepository $userRepo;
    
    public function operatorQueries(): void
    {
        // Comparison operators
        $results = $this->userRepo->fetch([
            ['age', '>', 18],              // Greater than
            ['age', '<=', 65],             // Less than or equal
            ['score', '>=', 100],          // Greater than or equal
            ['status', '!=', 'banned'],    // Not equal
        ]);
        
        // Alternative operator syntax
        $results = $this->userRepo->fetch([
            ['age', 'gt', 18],             // Same as '>'
            ['age', 'lte', 65],            // Same as '<='
            ['score', 'gte', 100],         // Same as '>='
            ['status', 'neq', 'banned'],   // Same as '!='
        ]);
        
        // Text operators
        $results = $this->userRepo->fetch([
            ['name', 'like', 'John%'],                    // SQL LIKE
            ['email', 'contains', 'gmail'],               // Contains substring
            ['username', 'starts_with', 'admin'],         // Starts with
            ['domain', 'ends_with', '.com'],              // Ends with
            ['description', 'not_like', '%spam%'],        // NOT LIKE
        ]);
        
        // NULL checks
        $results = $this->userRepo->fetch([
            ['deletedAt', 'is_null', true],               // IS NULL
            ['verifiedAt', 'is_not_null', true],          // IS NOT NULL
        ]);
        
        // IN and NOT IN
        $results = $this->userRepo->fetch([
            ['status', 'in', ['active', 'pending', 'verified']],
            ['role', 'not_in', ['banned', 'suspended']],
        ]);
        
        // BETWEEN
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');
        $results = $this->userRepo->fetch([
            ['created', 'between', [$startDate, $endDate]],
            ['age', 'not_between', [13, 17]],  // Exclude teenagers
        ]);
    }
}

// Example 4: Complex Criteria with OR/AND
// ========================================

class ComplexCriteriaExamples
{
    private UserRepository $userRepo;
    
    public function complexQueries(): void
    {
        // OR condition
        $results = $this->userRepo->fetch([
            ['or', [
                'status' => 'active',
                'role' => 'admin',
                'verified' => true
            ]]
        ]);
        // This finds users where status='active' OR role='admin' OR verified=true
        
        // Nested conditions
        $results = $this->userRepo->fetch([
            'type' => 'customer',
            ['or', [
                ['status' => 'premium'],
                ['and', [
                    'status' => 'active',
                    ['purchases', '>', 10]
                ]]
            ]]
        ]);
        // This finds customers who are either premium OR (active AND have > 10 purchases)
        
        // Complex nested example
        $results = $this->userRepo->fetch([
            ['or', [
                ['and', [
                    'role' => 'admin',
                    'active' => true
                ]],
                ['and', [
                    'role' => 'moderator',
                    'permissions' => 'full'
                ]],
                'super_admin' => true
            ]]
        ]);
        // Finds: (role='admin' AND active=true) OR (role='moderator' AND permissions='full') OR super_admin=true
    }
}

// Example 5: Working with Relationships
// ======================================

class RelationshipExamples
{
    private UserRepository $userRepo;
    
    public function relationshipQueries(): void
    {
        // Query on related entities using dot notation
        $results = $this->userRepo->fetch([
            'p.country' => 'USA',          // Profile country
            'c.name' => 'Acme Corp',       // Company name
        ]);
        
        // This works because we defined joins in the repository:
        // ['leftJoin', 'u.profile', 'p'],
        // ['leftJoin', 'u.company', 'c'],
        
        // Complex relationship queries
        $results = $this->userRepo->fetch([
            'status' => 'active',
            'p.verified' => true,
            ['c.employees', '>', 100],
            ['or', [
                'p.country' => 'USA',
                'p.country' => 'Canada'
            ]]
        ]);
    }
}

// Example 6: Building Custom Queries
// ===================================

class CustomQueryExamples
{
    private UserRepository $userRepo;
    
    public function customQueries(): void
    {
        // Get the Doctrine QueryBuilder for complex custom queries
        $qb = $this->userRepo->buildQuery(
            criteria: ['status' => 'active'],
            orderBy: ['created' => 'DESC']
        );
        
        // Add custom DQL
        $qb->andWhere('u.lastLogin > :date')
           ->setParameter('date', new DateTime('-30 days'));
        
        // Add custom select
        $qb->select('u', 'COUNT(p.id) as postCount')
           ->leftJoin('u.posts', 'p')
           ->groupBy('u.id');
        
        // Execute the query
        $results = $qb->getQuery()->getResult();
    }
}

// Example 7: Error Handling
// ==========================

class ErrorHandlingExamples
{
    private UserRepository $userRepo;
    
    public function safeQueries(): void
    {
        try {
            // This might return null
            $user = $this->userRepo->fetchOne(['email' => 'nonexistent@example.com']);
            
            if ($user === null) {
                // Handle not found
                throw new \Exception('User not found');
            }
            
            // Process user...
            
        } catch (\Exception $e) {
            // Handle errors
            error_log($e->getMessage());
        }
        
        // Check if results exist before processing
        $users = $this->userRepo->fetch(['status' => 'active']);
        
        if (empty($users)) {
            echo "No active users found\n";
            return;
        }
        
        foreach ($users as $user) {
            // Process each user
        }
    }
}