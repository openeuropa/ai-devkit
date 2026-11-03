<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Guardrail;

/**
 * Decision logic for the PreToolUse hook: block a shell command or a file read, or let it through.
 *
 * Shell rules are evaluated per command segment (split on `;`, `&&`, `||` and `|`) so that a protected branch
 * name or a file pattern in one segment does not taint another; the pipe-to-shell rule sees the whole line.
 * File-read tools (Kiro's own `read_file`, `list_directory`) carry one path, matched against the same
 * secret-file list and `.aiignore` globs the shell rules use. This is a tripwire for an agent's ordinary
 * actions, not a sandbox: a path assembled at run time gets past it, a hook sees a search tool's arguments
 * but not its results, and the DevOps image's egress firewall, not this class, is what stops traffic to
 * other hosts.
 */
final class Guardrail
{
    /** Tool IDs Kiro sends in the hook payload for the shell tool. */
    public const SHELL_TOOLS = ['execute_bash'];

    /** Tool IDs for Kiro's own file-read tools; each carries the path it opens in `tool_input.path`. */
    public const READ_TOOLS = ['read_file', 'list_directory'];

    /** Hosts curl and wget may contact; matches the egress allowlist of the DevOps image. */
    private const ALLOWED_HOSTS = [
        'packagist.org',
        'repo.packagist.org',
        'registry.npmjs.org',
        'github.com', 'api.github.com',
        'git.drupalcode.org',
        'drupal.org',
        'www.drupal.org',
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
        if (!\is_array($data)) {
            return null;
        }
        $tool = $data['tool_name'] ?? null;
        if (\in_array($tool, self::READ_TOOLS, true)) {
            return self::decideRead($data, $aiignoreGlobs);
        }
        if (!\in_array($tool, self::SHELL_TOOLS, true)) {
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
     * Applies the secret-file and `.aiignore` rules to a file-read tool's single path.
     *
     * Kiro sends an absolute path in `tool_input.path`. The secret-file list matches on any path tail, so it
     * works on the absolute path as it does on a shell argument; the `.aiignore` globs are project-relative,
     * so the path is first made relative to the payload's `cwd` before they are tried.
     *
     * @param array<string, mixed> $data          the decoded hook payload
     * @param list<string>         $aiignoreGlobs paths from .aiignore that must never be read
     *
     * @return string|null the reason to block, or null to allow
     */
    private static function decideRead(array $data, array $aiignoreGlobs): ?string
    {
        $input = $data['tool_input'] ?? null;
        $path = \is_array($input) ? ($input['path'] ?? null) : null;
        if (!\is_string($path) || '' === $path) {
            return null;
        }

        if (self::namesASecretFile($path)) {
            return 'reading files that hold credentials or database dumps is not allowed';
        }

        $cwd = $data['cwd'] ?? null;
        $relative = \is_string($cwd) && str_starts_with($path, rtrim($cwd, '/').'/')
            ? substr($path, \strlen(rtrim($cwd, '/')) + 1)
            : $path;
        if (null !== $reason = self::matchesAiignore($relative, $aiignoreGlobs)) {
            return $reason;
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
            if (self::namesASecretFile($arg)) {
                return 'reading files that hold credentials or database dumps is not allowed';
            }
            if (null !== $reason = self::matchesAiignore($arg, $aiignoreGlobs)) {
                return $reason;
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

    /**
     * Whether an argument or path names one of the credential or database-dump files.
     *
     * Matched on the tail of the string so an absolute path, a redirection target or a `cat .env` argument are
     * all caught, and ignoring case as macOS file systems do. `.env.dist` and `.env.example` are templates and
     * stay readable, as the SECRET_FILES patterns spell out.
     *
     * @param string $arg argument or path to inspect
     */
    private static function namesASecretFile(string $arg): bool
    {
        $secret = '(?:'.implode('|', self::SECRET_FILES).')';

        return '' !== $arg && 1 === preg_match('/(?:^|[\/=<>])'.$secret.'$/i', $arg);
    }

    /**
     * Whether a (project-relative) path is covered by an `.aiignore` glob.
     *
     * @param list<string> $aiignoreGlobs paths from .aiignore that must never be read
     *
     * @return string|null the reason to block, or null when no glob matches
     */
    private static function matchesAiignore(string $arg, array $aiignoreGlobs): ?string
    {
        foreach ($aiignoreGlobs as $glob) {
            if ('' !== $arg && (fnmatch($glob, $arg, \FNM_CASEFOLD) || fnmatch($glob, basename($arg), \FNM_CASEFOLD))) {
                return sprintf('%s is listed in .aiignore', $arg);
            }
        }

        return null;
    }
}
