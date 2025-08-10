<?php
/**
 * Pagination Examples for Doctrix
 * 
 * This file demonstrates the built-in pagination features
 */

use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Pagination\PaginationResult;
use Symfony\Component\HttpFoundation\Request;

class ArticleRepository extends BaseRepository
{
    protected string $alias = 'a';
}

// Example 1: Basic Pagination
// ============================

class BasicPaginationExamples
{
    private ArticleRepository $articleRepo;
    
    public function basicPagination(Request $request): void
    {
        // Simple pagination
        $page = $request->query->getInt('page', 1);
        $perPage = 20;
        
        $articles = $this->articleRepo->paginate(
            criteria: ['status' => 'published'],
            page: $page,
            perPage: $perPage
        );
        
        // Access pagination data
        echo "Page {$articles->page} of {$articles->lastPage}\n";
        echo "Showing {$articles->from} to {$articles->to} of {$articles->total} results\n";
        echo "Has next page: " . ($articles->hasMore ? 'Yes' : 'No') . "\n";
        echo "Has previous page: " . ($articles->hasPrevious ? 'Yes' : 'No') . "\n";
        
        // Iterate over items
        foreach ($articles as $article) {
            echo "- {$article->getTitle()}\n";
        }
    }
    
    public function paginationWithOrdering(Request $request): void
    {
        // Pagination with custom ordering
        $articles = $this->articleRepo->paginate(
            criteria: ['status' => 'published'],
            page: $request->query->getInt('page', 1),
            perPage: 15,
            orderBy: ['publishedAt' => 'DESC', 'title' => 'ASC']
        );
        
        // Check if empty
        if ($articles->isEmpty()) {
            echo "No articles found\n";
            return;
        }
        
        // Process articles
        foreach ($articles as $article) {
            // Process each article
        }
    }
}

// Example 2: Fluent Interface Pagination
// =======================================

class FluentPaginationExamples
{
    private ArticleRepository $articleRepo;
    
    public function fluentPagination(Request $request): void
    {
        // Using fluent interface with pagination
        $articles = $this->articleRepo->query()
            ->where('status', 'published')
            ->where('featured', true)
            ->whereNotNull('publishedAt')
            ->orderBy('publishedAt', 'DESC')
            ->paginate(
                page: $request->query->getInt('page', 1),
                perPage: 10
            );
        
        // All pagination features work the same
        echo "Total featured articles: {$articles->total}\n";
        echo "Current page: {$articles->page}\n";
        echo "Items on this page: " . count($articles) . "\n";
    }
    
    public function simplePagination(Request $request): void
    {
        // Simple pagination (for infinite scroll / load more)
        // Returns only items and hasMore flag, no total count
        $result = $this->articleRepo->query()
            ->where('status', 'published')
            ->orderBy('created', 'DESC')
            ->simplePaginate(
                page: $request->query->getInt('page', 1),
                perPage: 20
            );
        
        // Simple result structure
        $items = $result['items'];
        $hasMore = $result['hasMore'];
        
        // Perfect for "Load More" buttons
        foreach ($items as $article) {
            // Render article
        }
        
        if ($hasMore) {
            // Show "Load More" button
        }
    }
    
    public function pageHelper(Request $request): void
    {
        // Using page() helper for manual pagination
        $page = $request->query->getInt('page', 1);
        $perPage = 20;
        
        $articles = $this->articleRepo->query()
            ->where('status', 'published')
            ->page($page, $perPage)  // Sets limit and offset automatically
            ->get();
        
        // Note: This returns plain array, not PaginationResult
        // Use when you need simple pagination without metadata
    }
}

// Example 3: Working with PaginationResult
// =========================================

class PaginationResultExamples
{
    private ArticleRepository $articleRepo;
    
    public function workingWithResult(): void
    {
        $result = $this->articleRepo->paginate(
            criteria: ['status' => 'published'],
            page: 2,
            perPage: 10
        );
        
        // Direct iteration (implements IteratorAggregate)
        foreach ($result as $article) {
            // Process article
        }
        
        // Check if empty (multiple ways)
        if ($result->isEmpty()) {
            echo "No results\n";
        }
        
        if (!$result->isNotEmpty()) {
            echo "No results\n";
        }
        
        if (count($result) === 0) {
            echo "No results\n";
        }
        
        // Array access (implements ArrayAccess)
        $firstArticle = $result[0];  // Get first item
        
        // Countable interface
        $itemCount = count($result);  // Count items on current page
        
        // Helper methods
        if ($result->onFirstPage()) {
            echo "You're on the first page\n";
        }
        
        if ($result->onLastPage()) {
            echo "You're on the last page\n";
        }
        
        if ($result->hasMorePages()) {
            echo "Next page: {$result->nextPage}\n";
        }
        
        if ($result->hasPreviousPages()) {
            echo "Previous page: {$result->previousPage}\n";
        }
    }
    
    public function transformResults(): void
    {
        $result = $this->articleRepo->paginate(
            criteria: ['status' => 'published'],
            page: 1,
            perPage: 10
        );
        
        // Map over results
        $transformed = $result->map(function($article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'summary' => substr($article->getContent(), 0, 100) . '...',
                'url' => '/articles/' . $article->getSlug(),
            ];
        });
        
        // Filter results
        $featured = $result->filter(function($article) {
            return $article->isFeatured();
        });
        
        // Convert to array
        $array = $result->toArray();
        /*
        [
            'items' => [...],
            'total' => 150,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 15,
            'from' => 1,
            'to' => 10,
            'has_more' => true,
            'has_previous' => false,
            'next_page' => 2,
            'previous_page' => null
        ]
        */
        
        // Get metadata only (without items)
        $meta = $result->meta();
    }
}

// Example 4: Pagination in Controllers
// =====================================

class ArticleController
{
    private ArticleRepository $articleRepo;
    
    public function index(Request $request): array
    {
        // Get filters from request
        $filters = [];
        
        if ($request->query->has('category')) {
            $filters['category'] = $request->query->get('category');
        }
        
        if ($request->query->has('author')) {
            $filters['author'] = $request->query->get('author');
        }
        
        // Always filter by published status
        $filters['status'] = 'published';
        
        // Get pagination parameters
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('per_page', 20);
        $sort = $request->query->get('sort', 'created');
        $direction = $request->query->get('direction', 'DESC');
        
        // Paginate with filters and sorting
        $articles = $this->articleRepo->paginate(
            criteria: $filters,
            page: $page,
            perPage: $perPage,
            orderBy: [$sort => $direction]
        );
        
        return [
            'articles' => $articles,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction
        ];
    }
    
    public function search(Request $request): array
    {
        $search = $request->query->get('q', '');
        $page = $request->query->getInt('page', 1);
        
        // Complex search with pagination
        $results = $this->articleRepo->query()
            ->where('status', 'published')
            ->where(function($q) use ($search) {
                $q->whereContains('title', $search)
                  ->orWhereContains('content', $search)
                  ->orWhereContains('tags', $search);
            })
            ->orderBy('relevance', 'DESC')  // Assuming you have a relevance field
            ->orderBy('publishedAt', 'DESC')
            ->paginate($page, 20);
        
        return [
            'results' => $results,
            'search' => $search
        ];
    }
}

// Example 5: Pagination in Twig Templates
// ========================================

/*
{% comment %}
In your Twig template (articles/index.html.twig):
{% endcomment %}

{# Check if there are results #}
{% if articles is not empty %}
    
    {# Display items - iterate directly over PaginationResult #}
    <div class="articles">
        {% for article in articles %}
            <article>
                <h2>{{ article.title }}</h2>
                <p>{{ article.summary }}</p>
                <a href="{{ path('article_show', {slug: article.slug}) }}">Read more</a>
            </article>
        {% endfor %}
    </div>
    
    {# Display pagination info #}
    <div class="pagination-info">
        Showing {{ articles.from }} to {{ articles.to }} of {{ articles.total }} results
    </div>
    
    {# Display pagination controls #}
    {% if articles.lastPage > 1 %}
        <nav class="pagination">
            {# Previous page #}
            {% if not articles.onFirstPage() %}
                <a href="{{ path('articles_index', {page: articles.previousPage}) }}">Previous</a>
            {% endif %}
            
            {# Page numbers #}
            {% for page in 1..articles.lastPage %}
                {% if page == articles.page %}
                    <span class="current">{{ page }}</span>
                {% else %}
                    <a href="{{ path('articles_index', {page: page}) }}">{{ page }}</a>
                {% endif %}
            {% endfor %}
            
            {# Next page #}
            {% if not articles.onLastPage() %}
                <a href="{{ path('articles_index', {page: articles.nextPage}) }}">Next</a>
            {% endif %}
        </nav>
    {% endif %}
    
{% else %}
    <p>No articles found.</p>
{% endif %}

{# For infinite scroll / AJAX loading #}
<div id="articles-container">
    {% for article in articles %}
        {% include 'articles/_article.html.twig' with {article: article} %}
    {% endfor %}
</div>

{% if articles.hasMore %}
    <button id="load-more" data-page="{{ articles.nextPage }}">Load More</button>
{% endif %}
*/

// Example 6: API Pagination
// ==========================

class ArticleApiController
{
    private ArticleRepository $articleRepo;
    
    public function index(Request $request): array
    {
        $articles = $this->articleRepo->paginate(
            criteria: ['status' => 'published'],
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('limit', 20)
        );
        
        // Return JSON response with pagination metadata
        return [
            'data' => array_map(fn($article) => [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'summary' => $article->getSummary(),
                'published_at' => $article->getPublishedAt()->format('Y-m-d H:i:s'),
            ], $articles->items),
            'meta' => [
                'total' => $articles->total,
                'per_page' => $articles->perPage,
                'current_page' => $articles->page,
                'last_page' => $articles->lastPage,
                'from' => $articles->from,
                'to' => $articles->to,
            ],
            'links' => [
                'first' => $this->generateUrl('api_articles', ['page' => 1]),
                'last' => $this->generateUrl('api_articles', ['page' => $articles->lastPage]),
                'prev' => $articles->previousPage 
                    ? $this->generateUrl('api_articles', ['page' => $articles->previousPage])
                    : null,
                'next' => $articles->nextPage
                    ? $this->generateUrl('api_articles', ['page' => $articles->nextPage])
                    : null,
            ],
        ];
    }
    
    private function generateUrl(string $route, array $params): string
    {
        // Your URL generation logic here
        return '/api/articles?' . http_build_query($params);
    }
}