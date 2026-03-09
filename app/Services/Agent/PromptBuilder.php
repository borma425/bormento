<?php

namespace App\Services\Agent;

use App\Models\Tenant;

class PromptBuilder
{
    private ?Tenant $tenant;

    public function __construct()
    {
        $this->tenant = app()->bound(Tenant::class) ? app(Tenant::class) : null;
    }

    private function getStoreName(): string
    {
        return $this->tenant ? $this->tenant->name : 'Our Store';
    }

    private function getCustomRules(): string
    {
        if ($this->tenant && is_array($this->tenant->ai_config) && !empty($this->tenant->ai_config['custom_rules'])) {
            return "\n[CUSTOM STORE RULES]\n- " . implode("\n- ", (array)$this->tenant->ai_config['custom_rules']);
        }
        return "";
    }

    /**
     * Gets the User-defined Global Persona or falls back to default.
     */
    private function getGlobalPersona(): string
    {
        if ($this->tenant && !empty($this->tenant->ai_config['global_persona'])) {
            return $this->tenant->ai_config['global_persona'];
        }
        return AgentPrompts::defaultGlobalPersona($this->tenant);
    }

    /**
     * Gets the User-defined Sales Guidelines or falls back to default.
     */
    private function getSalesGuidelines(): string
    {
        $guidelines = "";
        if ($this->tenant && !empty($this->tenant->ai_config['sales_guidelines'])) {
            $guidelines = "[SALES & BRAND GUIDELINES]\n" . $this->tenant->ai_config['sales_guidelines'];
        } else {
            $guidelines = "[SALES & BRAND GUIDELINES]\n" . AgentPrompts::defaultSalesGuidelines($this->tenant);
        }
        return $guidelines . $this->getCustomRules();
    }

    /**
     * Agent: Greeting
     */
    private function greeting(): string
    {
        $userPrompt = $this->tenant->ai_config['greeting_agent_prompt'] ?? AgentPrompts::defaultGreetingPrompt($this->tenant);
        return "[GOAL & WORKFLOW]\n" . $userPrompt . "\n\n" . AgentPrompts::GREETING_SYSTEM_RULES;
    }

    /**
     * Agent: Catalog
     */
    private function catalog(): string
    {
        $userPrompt = $this->tenant->ai_config['catalog_agent_prompt'] ?? AgentPrompts::defaultCatalogPrompt($this->tenant);
        return "[GOAL & WORKFLOW]\n" . $userPrompt . "\n\n" . AgentPrompts::CATALOG_SYSTEM_RULES;
    }

    /**
     * Agent: Ordering
     */
    private function ordering(): string
    {
        $userPrompt = $this->tenant->ai_config['ordering_agent_prompt'] ?? AgentPrompts::defaultOrderingPrompt($this->tenant);
        return "[GOAL & WORKFLOW]\n" . $userPrompt . "\n\n" . AgentPrompts::ORDERING_SYSTEM_RULES;
    }

    /**
     * Builds the final prompt combining User Business Logic and unbreakable System Guardrails.
     */
    public function getFullPrompt(string $agentType, string $agentName, string $userName): string
    {
        $storeName = $this->getStoreName();
        $prompt = "You are {$agentName} at {$storeName}.\nThe user's name is: {$userName}.\n\n";

        // Inject Dynamic SaaS Settings
        if ($this->tenant) {
            $prompt .= "=== STORE CONFIGURATION ===\n";
            if ($this->tenant->reply_only_mode) {
                $prompt .= "- MODE: [REPLY ONLY]\n";
                $prompt .= "- VITAL RULE: You are acting strictly as a smart assistant to answer questions and provide information. You MUST NEVER ask the user to pay, checkout, or create an order. Simply gather their inquiries or lead data.\n";
            }
            if (!empty($this->tenant->shipping_zones)) {
                $prompt .= "- DELIVERY FEES BY GOVERNORATE:\n";
                foreach ($this->tenant->shipping_zones as $zone) {
                    if (isset($zone['governorate']) && isset($zone['fee'])) {
                        $prompt .= "  * {$zone['governorate']}: {$zone['fee']} EGP\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        // 1. Business Logic (Editable by Store Owner)
        $prompt .= "=== GLOBAL PERSONA ===\n";
        $prompt .= $this->getGlobalPersona() . "\n\n";

        $prompt .= "=== STORE GUIDELINES ===\n";
        $prompt .= $this->getSalesGuidelines() . "\n\n";

        // 2. Agent Specific Logic (Editable + Locked)
        $prompt .= "=== AGENT SPECIFIC ({$agentType}) ===\n";
        switch ($agentType) {
            case 'greeting':
                $prompt .= $this->greeting() . "\n\n";
                break;
            case 'catalog':
                $prompt .= $this->catalog() . "\n\n";
                break;
            case 'ordering':
                $prompt .= $this->ordering() . "\n\n";
                break;
            default:
                throw new \InvalidArgumentException("Unknown agent type: {$agentType}");
        }

        // 3. System Guardrails (Locked - Cannot be overridden)
        $prompt .= "=== SYSTEM GUARDRAILS (CRITICAL) ===\n";
        $prompt .= AgentPrompts::SYSTEM_CORE_DIRECTIVES . "\n";

        return $prompt;
    }
}
