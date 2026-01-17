<?php

namespace App\Services;

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
     * Check if a template has an approved SID.
     */
    public function isApproved(string $type): bool
    {
        $template = $this->getTemplate($type);

        return $template && ! empty($template['sid']);
    }

    /**
     * Get the approved template SID.
     */
    public function getContentSid(string $type): ?string
    {
        $template = $this->getTemplate($type);

        return $template['sid'] ?? null;
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
