<?php

namespace App\Services;

use App\Models\Setting;

class WhatsAppTemplateService
{
    /**
     * Get template configuration by type.
     */
    public function getTemplate(string $type): ?array
    {
        return config("whatsapp.templates.{$type}");
    }

    /**
     * Check if a template has an approved SID configured for the landlord.
     */
    public function isApproved(string $type, int $landlordId): bool
    {
        return ! empty($this->getContentSid($type, $landlordId));
    }

    /**
     * Get the approved template SID from landlord's settings.
     */
    public function getContentSid(string $type, int $landlordId): ?string
    {
        return Setting::get("whatsapp_template_{$type}_sid", null, $landlordId);
    }

    /**
     * Map named data to numbered variables for WhatsApp template.
     *
     * Converts ['tenant_name' => 'John', 'amount' => '15000'] to ['1' => 'John', '2' => '15000']
     * based on the template's variable order.
     */
    public function renderVariables(string $type, array $data): array
    {
        $template = $this->getTemplate($type);

        if (! $template || empty($template['variables'])) {
            return [];
        }

        $variables = [];
        $position = 1;

        foreach ($template['variables'] as $variableName) {
            $variables[(string) $position] = (string) ($data[$variableName] ?? '');
            $position++;
        }

        return $variables;
    }

    /**
     * Get all available template types.
     */
    public function getAvailableTemplates(): array
    {
        return array_keys(config('whatsapp.templates', []));
    }

    /**
     * Get all templates with their current SID status for a landlord.
     */
    public function getTemplatesWithStatus(int $landlordId): array
    {
        $templates = config('whatsapp.templates', []);
        $result = [];

        foreach ($templates as $type => $template) {
            $result[] = [
                'type' => $type,
                'name' => $template['name'],
                'label' => $template['label'] ?? ucwords(str_replace('_', ' ', $type)),
                'content' => $template['content'],
                'variables' => $template['variables'],
                'sid' => $this->getContentSid($type, $landlordId),
                'configured' => $this->isApproved($type, $landlordId),
            ];
        }

        return $result;
    }

    /**
     * Validate that all required variables are provided for a template.
     */
    public function validateVariables(string $type, array $data): array
    {
        $template = $this->getTemplate($type);
        $missing = [];

        if (! $template || empty($template['variables'])) {
            return $missing;
        }

        foreach ($template['variables'] as $variableName) {
            if (! isset($data[$variableName]) || $data[$variableName] === '') {
                $missing[] = $variableName;
            }
        }

        return $missing;
    }

    /**
     * Get the template content with placeholders for preview.
     */
    public function getTemplateContent(string $type): ?string
    {
        $template = $this->getTemplate($type);

        return $template['content'] ?? null;
    }

    /**
     * Render template content with actual values for fallback/preview.
     */
    public function renderContent(string $type, array $data): ?string
    {
        $template = $this->getTemplate($type);

        if (! $template) {
            return null;
        }

        $content = $template['content'];
        $position = 1;

        foreach ($template['variables'] as $variableName) {
            $value = $data[$variableName] ?? '';
            $content = str_replace("{{{$position}}}", $value, $content);
            $position++;
        }

        return $content;
    }
}
