<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Guardrail;

/**
 * Decision logic for the PreToolUse hook: block a shell command, or let it through.
 *
 * Rules are evaluated per command segment (split on `;`, `&&`, `||` and `|`) so that a protected branch
 * name or a file pattern in one segment does not taint another; the pipe-to-shell rule sees the whole line.
 * This is a tripwire for an agent's ordinary commands, not a sandbox: a path assembled at run time gets past
 * it, and the DevOps image's egress firewall, not this class, is what stops traffic to other hosts.
 */
final class Guardrail
{
    /** Tool names Kiro has used for the shell tool (CLI 2.x legacy name and the v3 name). */
    private const SHELL_TOOLS = ['execute_bash', 'shell'];

    /** Hosts curl and wget may contact; matches the egress allowlist of the DevOps image. */
    private const ALLOWED_HOSTS = [
        'packagist.org', 'repo.packagist.org', 'registry.npmjs.org', 'github.com', 'api.github.com',
        'git.drupalcode.org', 'drupal.org', 'www.drupal.org',
    ];

    /**
     * Files that hold credentials or personal data. No argument of any command may name one, whichever program
     * reads it, so `tac .env` is blocked like `cat .env`. Matched ignoring case, as macOS file systems do.
     */
    private const SECRET_FILES = [
        'sites\/[^\s\/]+\/settings[^\s\/]*\.php', // Drupal settings.php, settings.local.php: database credentials, hash salt.
        'runner\.yml',                            // Toolkit's local settings; runner.yml.dist is the committed template.
        '\.env(?!\.(?:example|dist)\b)[^\s\/]*',  // .env, .env.local; .env.example and .env.dist are templates.
        'dbs\/\S*',                               // Database dumps Toolkit downloads.
        '[^\s\/]+\.sql(?:\.gz)?',                 // Database dumps.
    ];

    /** Interpreters whose inline code (`-c`, `-r`, `-e`) is searched for secret file names too. */
    private const INTERPRETERS = ['sh', 'bash', 'zsh', 'php', 'python', 'python3', 'perl', 'ruby', 'node'];

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

        if (preg_match('/\b(?:curl|wget)\b[^|]*\|\s*(?:sudo\s+(?:-\w+\s+)*)?(?:sh|bash|zsh|php|python3?|perl)\b/i', $command)
            || preg_match('/\b(?:sh|bash|zsh)\s+<\(\s*(?:curl|wget)\b/i', $command)) {
            return 'piping a download into an interpreter is not allowed';
        }

        foreach (array_filter(array_map('trim', preg_split('/&&|[;|]/', $command) ?: [])) as $segment) {
            if (null !== $reason = self::decideSegment($segment, $aiignoreGlobs)) {
                return $reason;
            }
        }
        foreach ($extraDenyPatterns as $pattern) {
            if (@preg_match($pattern, $command)) {
                return sprintf('blocked by the project guardrail pattern %s', $pattern);
            }
        }

        return null;
    }

    /**
     * Applies the file, network and push rules to one command segment.
     *
     * @param string       $segment       one command of the line, without its separators
     * @param list<string> $aiignoreGlobs paths from .aiignore that must never be read
     *
     * @return string|null the reason to block, or null to allow
     */
    private static function decideSegment(string $segment, array $aiignoreGlobs): ?string
    {
        // Words, keeping a quoted string as one word so a commit message naming runner.yml is not a file argument.
        preg_match_all('/"[^"]*"|\'[^\']*\'|[^\s"\']+/', $segment, $m);
        $words = array_map(fn (string $w) => trim($w, '"\'()`'), $m[0]);
        // Wrappers and leading VAR=value assignments run the next word as the command.
        while (\in_array($words[0] ?? '', ['sudo', 'rtk', 'env'], true) || preg_match('/^[A-Za-z_]\w*=/', $words[0] ?? '')) {
            array_shift($words);
        }
        $first = strtolower(basename((string) array_shift($words)));
        $args = $words;

        $secret = '(?:'.implode('|', self::SECRET_FILES).')';
        foreach ($args as $arg) {
            if (preg_match('/(?:^|[\/=<>])'.$secret.'$/i', $arg)) {
                return 'reading files that hold credentials or database dumps is not allowed';
            }
            foreach ($aiignoreGlobs as $glob) {
                if ('' !== $arg && (fnmatch($glob, $arg, \FNM_CASEFOLD) || fnmatch($glob, basename($arg), \FNM_CASEFOLD))) {
                    return sprintf('%s is listed in .aiignore', $arg);
                }
            }
        }
        if (\in_array($first, self::INTERPRETERS, true) && array_intersect($args, ['-c', '-r', '-e'])
            && preg_match('/(?:^|[\s\/=<>\'"(])'.$secret.'(?=$|[\s\'"\\\\)])/i', $segment)) {
            return 'reading files that hold credentials or database dumps is not allowed';
        }

        if (\in_array($first, ['nc', 'ncat', 'netcat', 'dig', 'nslookup'], true)) {
            return 'network reconnaissance tools are not allowed';
        }
        if (\in_array($first, ['curl', 'wget'], true)) {
            foreach ($args as $arg) {
                if (str_starts_with($arg, '-')) {
                    continue;
                }
                // A URL (whatever its host form: name, IPv4, [IPv6], decimal) or a dotted host name.
                $host = str_contains($arg, '://') ? (string) parse_url($arg, \PHP_URL_HOST)
                    : (preg_match('#^([a-z0-9-]+(?:\.[a-z0-9-]+)+)(?::\d+)?(?:/|$)#i', $arg, $h) ? $h[1] : null);
                if (null !== $host && !\in_array(strtolower(trim($host, '[]')), self::ALLOWED_HOSTS, true)) {
                    return sprintf('network access to %s is outside the allowlist', '' !== $host ? $host : $arg);
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
