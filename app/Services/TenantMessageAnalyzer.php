<?php

namespace App\Services;

use App\Models\TenantMessage;

class TenantMessageAnalyzer
{
    protected const KEYWORD_PATTERNS = [
        TenantMessage::ACTION_YES => '/^(yes|yeah|ok|okay|confirm|accept|approved?)\s*$/i',
        TenantMessage::ACTION_NO => '/^(no|nope|decline|reject|cancel)\s*$/i',
        TenantMessage::ACTION_HELP => '/\b(help|support|assist|question)\b/i',
        TenantMessage::ACTION_ISSUE => '/\b(broken|problem|issue|repair|fix|leak|water|electricity|plumbing|not working|doesn\'t work|stopped working)\b/i',
        TenantMessage::ACTION_PAYMENT => '/\b(pay|payment|mpesa|paybill|invoice)\b/i',
    ];

    protected const URGENT_PATTERNS = [
        '/\b(urgent|urgently|emergency|asap|immediately)\b/i',
        '/\b(flood|flooding|flooded)\b/i',
        '/\b(fire|smoke|burning)\b/i',
        '/\b(no water|no electricity|no power|blackout)\b/i',
        '/\b(locked out|can\'t get in|stuck)\b/i',
        '/\b(sewage|overflow|burst)\b/i',
        '/\b(dangerous|hazard|safety)\b/i',
    ];

    protected const SUBCATEGORY_PATTERNS = [
        'plumbing' => '/\b(plumb|pipe|drain|tap|faucet|sink|toilet|shower|leak)\b/i',
        'electrical' => '/\b(electric|power|socket|switch|light|bulb|wire|outlet)\b/i',
        'water_supply' => '/\b(water|tank|pump|supply)\b/i',
        'structural' => '/\b(wall|floor|ceiling|door|window|roof|crack)\b/i',
        'appliances' => '/\b(fridge|stove|oven|washer|dryer|appliance|heater)\b/i',
        'pest_control' => '/\b(pest|insect|bug|rat|mice|cockroach|ant)\b/i',
    ];

    public function analyze(string $body): array
    {
        $trimmedBody = trim($body);

        return [
            'action_type' => $this->detectActionKeyword($trimmedBody),
            'urgency' => $this->detectUrgency($trimmedBody),
            'subcategory' => $this->detectSubcategory($trimmedBody),
            'is_issue' => $this->containsIssueKeywords($trimmedBody),
            'matched_keywords' => $this->extractKeywordMatches($trimmedBody),
        ];
    }

    public function detectActionKeyword(string $body): ?string
    {
        $trimmedBody = trim($body);

        foreach (self::KEYWORD_PATTERNS as $action => $pattern) {
            if (preg_match($pattern, $trimmedBody)) {
                return $action;
            }
        }

        return null;
    }

    public function detectUrgency(string $body): string
    {
        foreach (self::URGENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                return 'urgent';
            }
        }

        return 'normal';
    }

    public function detectSubcategory(string $body): string
    {
        foreach (self::SUBCATEGORY_PATTERNS as $subcategory => $pattern) {
            if (preg_match($pattern, $body)) {
                return $subcategory;
            }
        }

        return 'other';
    }

    public function containsIssueKeywords(string $body): bool
    {
        return (bool) preg_match(self::KEYWORD_PATTERNS[TenantMessage::ACTION_ISSUE], $body);
    }

    public function extractKeywordMatches(string $body): array
    {
        $matches = [];

        foreach (self::KEYWORD_PATTERNS as $action => $pattern) {
            if (preg_match_all($pattern, $body, $found)) {
                $matches[$action] = array_unique($found[0]);
            }
        }

        foreach (self::URGENT_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $body, $found)) {
                $matches['urgent'] = array_merge($matches['urgent'] ?? [], $found[0]);
            }
        }

        if (isset($matches['urgent'])) {
            $matches['urgent'] = array_unique($matches['urgent']);
        }

        return $matches;
    }

    public function determinePriority(string $body): string
    {
        $urgency = $this->detectUrgency($body);

        if ($urgency === 'urgent') {
            return 'high';
        }

        return 'medium';
    }

    public function determineCategory(string $actionType): string
    {
        return $actionType === TenantMessage::ACTION_ISSUE ? 'issue' : 'complaint';
    }
}
