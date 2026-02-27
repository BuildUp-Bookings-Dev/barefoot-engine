<?php

namespace BarefootEngine\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Loader
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $actions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $filters = [];

    /**
     * Register an action hook.
     */
    public function add_action(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->actions[] = $this->set_hook($hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Register a filter hook.
     */
    public function add_filter(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->filters[] = $this->set_hook($hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Execute hook registration with WordPress.
     */
    public function run(): void
    {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function set_hook(string $hook, object $component, string $callback, int $priority, int $accepted_args): array
    {
        return [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}
