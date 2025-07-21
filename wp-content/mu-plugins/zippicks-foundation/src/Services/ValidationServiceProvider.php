<?php
/**
 * Validation Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Validation\Validator;

/**
 * Provides validation services to the foundation
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register the validation services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register validator as a transient (new instance each time)
        $this->bind(ValidatorInterface::class, Validator::class);

        // Register alias for easier access if not already defined
        $container = $this->foundation->getContainer();
        if (!$container->has('validator')) {
            $container->alias('validator', ValidatorInterface::class);
        }
    }

    /**
     * Bootstrap the validation services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Log validation service initialization if logging is available
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $logger->channel('validation')->info('Validation service initialized', [
                'validator' => Validator::class,
                'default_rules' => ['required', 'email', 'min_length', 'max_length'],
            ]);
        }

        // Register any custom rules from configuration
        $this->registerCustomRules();
    }

    /**
     * Register custom validation rules from configuration
     * 
     * @return void
     */
    protected function registerCustomRules(): void
    {
        // Get settings if available
        $settings = null;
        if ($this->has('settings')) {
            $settings = $this->get('settings');
        }

        if (!$settings) {
            return;
        }

        // Get custom rules from configuration
        $customRules = $settings->get('validation.custom_rules', []);

        foreach ($customRules as $name => $ruleClass) {
            if (class_exists($ruleClass)) {
                try {
                    $rule = new $ruleClass();
                    
                    // Get a fresh validator instance to add the rule
                    $validator = $this->get(ValidatorInterface::class);
                    $validator->addRule($name, $rule);
                    
                    if ($this->has('logger')) {
                        $this->get('logger')->channel('validation')->debug(
                            'Registered custom validation rule',
                            ['name' => $name, 'class' => $ruleClass]
                        );
                    }
                } catch (\Throwable $e) {
                    if ($this->has('logger')) {
                        $this->get('logger')->channel('validation')->error(
                            'Failed to register custom validation rule',
                            [
                                'name' => $name,
                                'class' => $ruleClass,
                                'error' => $e->getMessage(),
                            ]
                        );
                    }
                }
            }
        }
    }
}