<?php

namespace WelshDev\Doctrix\Traits;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Trait for adding macro functionality to classes
 *
 * Allows runtime registration of custom methods that can be chained
 * in fluent interfaces
 */
trait MacroableTrait
{
    /**
     * The registered string macros
     *
     * @var array<string, Closure>
     */
    protected static array $macros = [];

    /**
     * Dynamically handle calls to macros
     *
     * @param string $method
     * @param array $parameters
     *
     * @throws BadMethodCallException
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasMacro($method))
        {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method,
            ));
        }

        $macro = static::$macros[static::class][$method];

        if ($macro instanceof Closure)
        {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Dynamically handle static calls to macros
     *
     * @param string $method
     * @param array $parameters
     *
     * @throws BadMethodCallException
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (!static::hasMacro($method))
        {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method,
            ));
        }

        $macro = static::$macros[static::class][$method];

        if ($macro instanceof Closure)
        {
            $macro = $macro->bindTo(null, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Register a custom macro
     *
     * @param string $name The macro name
     * @param Closure $macro The macro implementation
     * @return void
     *
     * @example
     * $repo->registerMacro('activeUsers', function($query) {
     *     return $query->where('status', 'active')->where('verified', true);
     * });
     */
    public function registerMacro(string $name, Closure $macro): void
    {
        static::$macros[static::class][$name] = $macro;
    }

    /**
     * Register multiple macros at once
     *
     * @param array<string, Closure> $macros
     * @return void
     *
     * @example
     * $repo->registerMacros([
     *     'active' => fn($q) => $q->where('status', 'active'),
     *     'verified' => fn($q) => $q->where('verified', true),
     *     'admins' => fn($q) => $q->where('role', 'admin')
     * ]);
     */
    public function registerMacros(array $macros): void
    {
        foreach ($macros as $name => $macro)
        {
            $this->registerMacro($name, $macro);
        }
    }

    /**
     * Mix another object's methods into the class
     *
     * @param object $mixin
     * @param bool $replace Whether to replace existing macros
     *
     * @throws ReflectionException
     * @return void
     */
    public static function mixin(object $mixin, bool $replace = true): void
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED,
        );

        foreach ($methods as $method)
        {
            if ($replace || !static::hasMacro($method->name))
            {
                $method->setAccessible(true);
                static::registerGlobalMacro($method->name, $method->invoke($mixin));
            }
        }
    }

    /**
     * Register a global macro that applies to all instances
     *
     * @param string $name
     * @param Closure $macro
     * @return void
     */
    public static function registerGlobalMacro(string $name, Closure $macro): void
    {
        static::$macros[static::class][$name] = $macro;
    }

    /**
     * Check if a macro is registered
     *
     * @param string $name
     * @return bool
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[static::class][$name]);
    }

    /**
     * Remove a registered macro
     *
     * @param string $name
     * @return void
     */
    public static function removeMacro(string $name): void
    {
        unset(static::$macros[static::class][$name]);
    }

    /**
     * Clear all registered macros
     *
     * @return void
     */
    public static function clearMacros(): void
    {
        static::$macros[static::class] = [];
    }

    /**
     * Get all registered macros
     *
     * @return array<string, Closure>
     */
    public static function getMacros(): array
    {
        return static::$macros[static::class] ?? [];
    }
}
