<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Guardrail;

/**
 * Decision logic for the PreToolUse hook: block a shell command, or let it through.
 *
 * Rules are evaluated per command segment (split on `;`, `&&`, `||` and `|`) so that a protected branch
 * name or a file pattern in one segment does not taint another; the pipe-to-shell rule sees the whole line.
 */
final class Guardrail
{
    /** Tool names Kiro has used for the shell tool (CLI 2.x legacy name and the v3 name). */
    private const SHELL_TOOLS = ['execute_bash', 'shell'];

    /** Hosts a shell command may contact; matches the egress allowlist of the DevOps image. */
    private const ALLOWED_HOSTS = [
        'packagist.org', 'repo.packagist.org', 'registry.npmjs.org', 'github.com', 'api.github.com',
        'git.drupalcode.org', 'drupal.org', 'www.drupal.org',
    ];

    private const READERS = '(?:cat|less|more|head|tail|sed|awk|grep|rg|ag|bat|strings|cut|sort|uniq|xxd|od|base64)';
    private const SECRET_FILES = '(?:sites\/[^\s\/]+\/settings[^\s]*\.php|runner\.yml(?!\.dist)|\.env(?!\.(?:example|dist))[^\s]*|dbs\/[^\s]*|[^\s]+\.sql(?:\.gz)?)';

    /**
     * @param string       $stdin             the hook payload (JSON with tool_name and tool_input)
     * @param list<string> $extraDenyPatterns project-provided PCRE patterns
     * @param list<string> $aiignoreGlobs     paths from .aiignore that must never be read
     *
     * @return string|null the reason to block, or null to allow
     */
    public static function decide(string $stdin, array $extraDenyPatterns = [], array $aiignoreGlobs = []): ?string
    {
        $data = json_decode($stdin, true);
        if (!\is_array($data) || !\in_array($data['tool_name'] ?? null, self::SHELL_TOOLS, true)) {
            return null;
        }
        $command = $data['tool_input']['command'] ?? null;
        if (!\is_string($command)) {
            return null;
        }

        if (preg_match('/\b(?:curl|wget)\b[^|]*\|\s*(?:sudo\s+(?:-\w+\s+)*)?(?:sh|bash|zsh|php|python3?|perl)\b/', $command)
            || preg_match('/\b(?:sh|bash|zsh)\s+<\(\s*(?:curl|wget)\b/', $command)) {
            return 'piping a download into an interpreter is not allowed';
        }

        foreach (array_filter(array_map('trim', preg_split('/&&|[;|]/', $command) ?: [])) as $segment) {
            if (null !== $reason = self::decideSegment($segment, $aiignoreGlobs)) {
                return $reason;
            }
        }
        foreach ($extraDenyPatterns as $pattern) {
            if (@preg_match($pattern, $command)) {
                return 'blocked by a project guardrail pattern';
            }
        }

        return null;
    }

    /**
     * @param list<string> $aiignoreGlobs
     */
    private static function decideSegment(string $segment, array $aiignoreGlobs): ?string
    {
        $words = preg_split('/\s+/', $segment) ?: [];
        if (\in_array($words[0] ?? '', ['sudo', 'rtk'], true)) {
            array_shift($words);
        }
        $first = (string) array_shift($words);
        $args = array_map(fn (string $w) => trim($w, '"\''), $words);

        if (\in_array($first, ['nc', 'ncat', 'netcat', 'dig', 'nslookup'], true)) {
            return 'network reconnaissance tools are not allowed';
        }
        if (\in_array($first, ['curl', 'wget'], true)) {
            foreach ($args as $arg) {
                // A URL or a dotted host name (or IPv4), never a flag or a bare word.
                if (!str_starts_with($arg, '-') && preg_match('#^(?:https?://)?([a-z0-9-]+(?:\.[a-z0-9-]+)+)(?::\d+)?(?:/|$)#i', $arg, $m) && !\in_array(strtolower($m[1]), self::ALLOWED_HOSTS, true)) {
                    return sprintf('network access to %s is outside the allowlist', $m[1]);
                }
            }
        }
        if (preg_match('/^'.self::READERS.'\b/', $first)) {
            if (preg_match('/(?:^|[\s\/=])'.self::SECRET_FILES.'(?=$|\s)/', ' '.implode(' ', $args))) {
                return 'reading files that hold credentials or database dumps is not allowed';
            }
            foreach ($args as $arg) {
                foreach ($aiignoreGlobs as $glob) {
                    if (fnmatch($glob, $arg) || fnmatch($glob, basename($arg))) {
                        return sprintf('%s is listed in .aiignore', $arg);
                    }
                }
            }
        }
        if ('git' === $first && 'push' === ($args[0] ?? '')) {
            foreach (\array_slice($args, 1) as $arg) {
                if (preg_match('/^(?:[^:]+:)?(?:refs\/heads\/)?(main|master|develop|release\/\S+)$/', $arg)) {
                    return 'pushing to a protected branch needs a human';
                }
            }
        }

        return null;
    }
}
