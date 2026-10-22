#!/usr/bin/env bash
# Run the oe-project-wiki behavioural evals against a provider CLI.
#
# Why this wrapper exists: the eval runner ships in vendor/ (upstream
# drupal/ai_best_practices) and its codex invocation targets an older codex
# CLI. For codex >= 0.161 it passes a removed flag (--full-auto) and runs in a
# tmp dir the new CLI refuses without --skip-git-repo-check. This script puts a
# throwaway shim first on PATH that rewrites those flags, so vendor/ stays
# untouched (it is regenerated on composer install). The claude/gemini/mistral
# providers are passed through unchanged.
#
# Usage:
#   scripts/run-behavioral-evals.sh                       # codex, model from $CODEX_MODEL or CLI default
#   scripts/run-behavioral-evals.sh --provider claude     # another provider, no shim
#   CODEX_MODEL=gpt-5.6-terra scripts/run-behavioral-evals.sh
#
# Extra args are forwarded to the runner, e.g. --skill, --output-dir.
set -euo pipefail

DEVKIT="$(cd "$(dirname "$0")/.." && pwd)"
RUNNER="$DEVKIT/vendor/drupal/ai_best_practices/evals/run-evals.py"
[ -f "$RUNNER" ] || { echo "runner not found: $RUNNER (run composer install)" >&2; exit 1; }

# Defaults; override --provider/--skill/--model/etc. by passing them as args.
PROVIDER="codex"
SKILL="oe-project-wiki"
OUTPUT_DIR="$DEVKIT/traces"
MODEL="${CODEX_MODEL:-}"

# Pull --provider out of the args so we know whether to install the codex shim.
# Everything else is forwarded verbatim.
FORWARD=()
while [ $# -gt 0 ]; do
  case "$1" in
    --provider) PROVIDER="$2"; FORWARD+=(--provider "$2"); shift 2 ;;
    --provider=*) PROVIDER="${1#*=}"; FORWARD+=("$1"); shift ;;
    *) FORWARD+=("$1"); shift ;;
  esac
done

# Ensure --provider reaches the runner even when the caller relied on the default.
case " ${FORWARD[*]} " in *" --provider "*|*" --provider="*) : ;; *) FORWARD+=(--provider "$PROVIDER") ;; esac

# Only codex gets a model default and the shim.
if [ "$PROVIDER" = "codex" ]; then
  # The runner hardcodes an unavailable default model (gpt-5.4). Prefer, in
  # order: --model already forwarded, $CODEX_MODEL, the model configured in
  # ~/.codex/config.toml. Only then fall through to the runner's default.
  if [ -z "$MODEL" ] && [ -f "$HOME/.codex/config.toml" ]; then
    MODEL="$(sed -n 's/^[[:space:]]*model[[:space:]]*=[[:space:]]*"\([^"]*\)".*/\1/p' "$HOME/.codex/config.toml" | head -n1)"
  fi
  case " ${FORWARD[*]} " in
    *" --model "*|*" --model="*) : ;;
    *) [ -n "$MODEL" ] && { echo "using codex model: $MODEL" >&2; FORWARD+=(--model "$MODEL"); } ;;
  esac

  SHIM="$(mktemp -d)"
  trap 'rm -rf "$SHIM"' EXIT
  cat > "$SHIM/codex" <<'SHIM_EOF'
#!/usr/bin/env bash
# Resolve the real codex, skipping this shim's own directory.
self_dir="$(cd "$(dirname "$0")" && pwd)"
real=""
IFS=':' read -ra parts <<< "$PATH"
for p in "${parts[@]}"; do
  [ "$p" = "$self_dir" ] && continue
  if [ -x "$p/codex" ]; then real="$p/codex"; break; fi
done
[ -n "$real" ] || { echo "real codex not found on PATH" >&2; exit 127; }
# --full-auto was removed in codex >= 0.161; map it to the current equivalents.
args=()
for a in "$@"; do
  case "$a" in
    --full-auto) args+=(--sandbox workspace-write --skip-git-repo-check) ;;
    *) args+=("$a") ;;
  esac
done
exec "$real" "${args[@]}"
SHIM_EOF
  chmod +x "$SHIM/codex"
  export PATH="$SHIM:$PATH"
fi

exec python3 "$RUNNER" \
  --behavioral \
  --skill "$SKILL" \
  --root "$DEVKIT" \
  --output-dir "$OUTPUT_DIR" \
  "${FORWARD[@]}"
