#!/usr/bin/env bash
# PreToolUse hook (matcher: Bash). Reads the tool input JSON from stdin.
# Exit 2 blocks the command; the reason on stderr goes back to the model, which then picks the safer alternative.
# Checks each command segment (&&, ||, ;, |) separately so a chain like "git push origin feat && gh pr view main" doesn't misfire.
# Text matching on commands is brittle: anything expressible as a permission rule belongs in the deny list in settings.json.
# This hook covers what the deny list can't express and names the alternative.
set -u
command -v jq >/dev/null || exit 0 # without jq, let commands through rather than blocking everything
cmd=$(jq -r '.tool_input.command // empty' 2>/dev/null) || exit 0
[ -z "$cmd" ] && exit 0

block() { echo "BLOCKED: $1" >&2; exit 2; }

IFS=$'\n' read -r -d '' -a segments < <(printf '%s' "$cmd" | sed -E 's/(&&|\|\||;|\|)/\n/g'; printf '\0')

for seg in "${segments[@]}"; do
  # unquote single words ("main" stays main), then drop quoted text with spaces,
  # so 'echo "rm -rf is dangerous"' or a commit message doesn't trigger a block
  s=$(printf '%s' "$seg" | sed -E "s/'([^' ]*)'/\1/g; s/\"([^\" ]*)\"/\1/g; s/'[^']*'//g; s/\"[^\"]*\"//g")
  if printf '%s' "$s" | grep -Eq '(^|\s)rm\s+(-[a-zA-Z]*r[a-zA-Z]*f|-[a-zA-Z]*f[a-zA-Z]*r|--recursive\s+--force|-r\s+-f|-f\s+-r)'; then
    block "recursive force delete is blocked. Use 'gio trash <path>' or 'trash <path>' (recoverable), or ask the developer."
  fi
  if printf '%s' "$s" | grep -Eq '(^|\s)git\s+push\b.*\b(main|master|develop)\b'; then
    block "pushing to main/master/develop is blocked. Push the feature branch and open a pull request."
  fi
  if printf '%s' "$s" | grep -Eq '(^|\s)git\s+(commit|push)\b.*--no-verify'; then
    block "skipping git hooks is blocked. Fix what the hook reports or ask the developer."
  fi
  if printf '%s' "$s" | grep -Eq '(^|\s)git\s+(clean\s+-[a-zA-Z]*f|checkout\s+\.\s*$|stash\s+(-u|--include-untracked))'; then
    block "repo-wide discard is blocked (untracked work has no way back). Restore named paths only."
  fi
done
exit 0
