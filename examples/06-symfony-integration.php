<?php
/**
 * Symfony Integration Examples for Doctrix
 * 
 * This file shows how to integrate Doctrix with Symfony components
 */

// Example 1: Repository as Symfony Service
// =========================================

// src/Repository/UserRepository.php
namespace App\Repository;

use App\Entity\User;
use WelshDev\Doctrix\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends BaseRepository<User>
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 */
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry);
    }
    
    /**
     * Find user by email (for Symfony Security)
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->fetchOne([
            'email' => $identifier,
            'active' => true
        ]);
    }
    
    /**
     * Upgrade password (for Symfony Security)
     */
    public function upgradePassword(UserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new \TypeError('Wrong user type');
        }
        
        $user->setPassword($newHashedPassword);
        // Note: The consuming application should handle persist/flush
        // $this->getEntityManager()->persist($user);
        // $this->getEntityManager()->flush();
    }
}

// Example 2: Controller with Repository Injection
// ================================================

// src/Controller/UserController.php
namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/users')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}
    
    #[Route('', name: 'user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [];
        
        // Build filters from request
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        
        if ($request->query->has('role')) {
            $filters['role'] = $request->query->get('role');
        }
        
        // Search
        $search = $request->query->get('q');
        if ($search) {
            $users = $this->userRepository->query()
                ->where(function($q) use ($search) {
                    $q->whereContains('name', $search)
                      ->orWhereContains('email', $search);
                })
                ->paginate(
                    page: $request->query->getInt('page', 1),
                    perPage: 20
                );
        } else {
            $users = $this->userRepository->paginate(
                criteria: $filters,
                page: $request->query->getInt('page', 1),
                perPage: 20,
                orderBy: ['created' => 'DESC']
            );
        }
        
        return $this->render('users/index.html.twig', [
            'users' => $users,
            'filters' => $filters,
            'search' => $search
        ]);
    }
    
    #[Route('/{id}', name: 'user_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->fetchOne(['id' => $id]);
        
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        
        return $this->render('users/show.html.twig', [
            'user' => $user
        ]);
    }
}

// Example 3: Form Type with Repository
// =====================================

// src/Form/OrderType.php
namespace App\Form;

use App\Entity\Order;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function __construct(
        private CustomerRepository $customerRepository
    ) {}
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'query_builder' => function() {
                    // Use Doctrix to build the query
                    return $this->customerRepository->buildQuery(
                        criteria: ['active' => true],
                        orderBy: ['name' => 'ASC']
                    );
                },
                'choice_label' => function(Customer $customer) {
                    return sprintf('%s (%s)', 
                        $customer->getName(),
                        $customer->getEmail()
                    );
                },
                'placeholder' => 'Select a customer...',
                'required' => true,
            ])
            ->add('orderDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('total', MoneyType::class, [
                'currency' => 'USD',
                'required' => true,
            ]);
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}

// Example 4: API Controller with Serialization
// =============================================

// src/Controller/Api/ProductApiController.php
namespace App\Controller\Api;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/products')]
class ProductApiController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private SerializerInterface $serializer
    ) {}
    
    #[Route('', name: 'api_products', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // Build query from request parameters
        $query = $this->productRepository->query();
        
        // Filters
        if ($category = $request->query->get('category')) {
            $query->where('category', $category);
        }
        
        if ($minPrice = $request->query->get('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice = $request->query->get('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }
        
        if ($search = $request->query->get('search')) {
            $query->whereContains('name', $search);
        }
        
        // Only active products
        $query->where('active', true);
        
        // Sorting
        $sort = $request->query->get('sort', 'created');
        $order = $request->query->get('order', 'DESC');
        $query->orderBy($sort, $order);
        
        // Paginate
        $products = $query->paginate(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('limit', 20)
        );
        
        // Serialize products
        $data = [
            'data' => json_decode(
                $this->serializer->serialize($products->items, 'json', [
                    'groups' => ['product:list']
                ]),
                true
            ),
            'meta' => $products->meta(),
            'links' => $this->generatePaginationLinks($products, $request)
        ];
        
        return new JsonResponse($data);
    }
    
    private function generatePaginationLinks($pagination, Request $request): array
    {
        $route = $request->attributes->get('_route');
        $params = $request->query->all();
        
        $links = [];
        
        // First page
        $links['first'] = $this->generateUrl($route, array_merge($params, ['page' => 1]));
        
        // Last page
        $links['last'] = $this->generateUrl($route, array_merge($params, ['page' => $pagination->lastPage]));
        
        // Previous page
        if ($pagination->previousPage) {
            $links['prev'] = $this->generateUrl($route, array_merge($params, ['page' => $pagination->previousPage]));
        } else {
            $links['prev'] = null;
        }
        
        // Next page
        if ($pagination->nextPage) {
            $links['next'] = $this->generateUrl($route, array_merge($params, ['page' => $pagination->nextPage]));
        } else {
            $links['next'] = null;
        }
        
        return $links;
    }
}

// Example 5: Event Subscriber with Repository
// ============================================

// src/EventSubscriber/UserActivitySubscriber.php
namespace App\EventSubscriber;

use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Doctrine\ORM\EntityManagerInterface;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
        ];
    }
    
    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if (!$user instanceof User) {
            return;
        }
        
        // Update last login
        $user->setLastLogin(new \DateTime());
        
        // Increment login count using Doctrix
        $qb = $this->userRepository->buildQuery(['id' => $user->getId()]);
        $qb->update()
           ->set('u.loginCount', 'u.loginCount + 1')
           ->getQuery()
           ->execute();
        
        // The consuming application handles when to flush
        // This gives better control over transaction boundaries
        $this->entityManager->flush();
    }
}

// Example 6: Command with Repository
// ===================================

// src/Command/CleanupUsersCommand.php
namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;

class CleanupUsersCommand extends Command
{
    protected static $defaultName = 'app:cleanup-users';
    protected static $defaultDescription = 'Clean up inactive users';
    
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Days of inactivity', 365)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run without deleting')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        
        $io->title('Cleaning up inactive users');
        
        // Find inactive users using Doctrix
        $inactiveDate = new \DateTime("-{$days} days");
        
        $users = $this->userRepository->query()
            ->where('lastLogin', '<', $inactiveDate)
            ->orWhereNull('lastLogin')
            ->where('created', '<', $inactiveDate)
            ->get();
        
        $count = count($users);
        
        if ($count === 0) {
            $io->success('No inactive users found.');
            return Command::SUCCESS;
        }
        
        $io->warning("Found {$count} inactive users.");
        
        if ($dryRun) {
            $io->note('Dry run mode - no users will be deleted.');
            
            // Show sample of users
            $io->table(
                ['ID', 'Email', 'Last Login', 'Created'],
                array_map(fn($user) => [
                    $user->getId(),
                    $user->getEmail(),
                    $user->getLastLogin()?->format('Y-m-d'),
                    $user->getCreated()->format('Y-m-d'),
                ], array_slice($users, 0, 10))
            );
        } else {
            if (!$io->confirm("Delete {$count} users?", false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
            
            $progressBar = $io->createProgressBar($count);
            
            foreach ($users as $user) {
                $this->entityManager->remove($user);
                $progressBar->advance();
            }
            
            // Flush all changes at once for efficiency
            $this->entityManager->flush();
            $progressBar->finish();
            
            $io->success("Deleted {$count} inactive users.");
        }
        
        return Command::SUCCESS;
    }
}

// Example 7: Service Configuration
// =================================

/*
# config/services.yaml

services:
    # Default configuration
    _defaults:
        autowire: true
        autoconfigure: true

    # Make Doctrix services available
    WelshDev\Doctrix\Service\QueryBuilderService:
        arguments:
            - '@doctrine.orm.entity_manager'

    # Auto-register all repositories extending BaseRepository
    App\Repository\:
        resource: '../src/Repository/'
        tags: ['doctrine.repository_service']

    # Register specific repository as service
    App\Repository\UserRepository:
        arguments:
            - '@doctrine'
        tags:
            - { name: 'doctrine.repository_service' }

# config/packages/doctrine.yaml

doctrine:
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
        
        # Enable custom repository factory if needed
        repository_factory: 'doctrine.orm.default_repository_factory'
*/

// Example 8: Twig Extension for Doctrix
// ======================================

// src/Twig/DoctrixExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use WelshDev\Doctrix\Pagination\PaginationResult;

class DoctrixExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('doctrix_pagination_info', [$this, 'paginationInfo']),
            new TwigFunction('doctrix_pagination_range', [$this, 'paginationRange']),
        ];
    }
    
    public function paginationInfo(PaginationResult $pagination): string
    {
        if ($pagination->total === 0) {
            return 'No results found';
        }
        
        return sprintf(
            'Showing %d to %d of %d results',
            $pagination->from,
            $pagination->to,
            $pagination->total
        );
    }
    
    public function paginationRange(PaginationResult $pagination, int $delta = 2): array
    {
        $range = [];
        
        $start = max(1, $pagination->page - $delta);
        $end = min($pagination->lastPage, $pagination->page + $delta);
        
        for ($i = $start; $i <= $end; $i++) {
            $range[] = $i;
        }
        
        return $range;
    }
}