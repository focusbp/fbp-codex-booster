<?php
namespace Fbp\ImageEffects;

use RuntimeException;

/** Execution context is supplied by server code, never by an image job or HTTP input. */
final class ImageAiRouting
{
    public static function provider(string $context): string
    {
        if (!in_array($context, ['php', 'auto-task'], true)) {
            throw new \InvalidArgumentException('Unknown image AI execution context.');
        }
        return $context === 'auto-task' || getenv('AUTO_TASK_CODEX') === '1' ? 'codex' : 'openai';
    }

    public static function requireApi(): void
    {
        if (self::provider('php') !== 'openai') {
            throw new RuntimeException('Auto-task must use the Codex image tool; API fallback is disabled.');
        }
    }
}
