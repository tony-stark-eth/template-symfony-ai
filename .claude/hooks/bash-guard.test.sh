#!/usr/bin/env bash
# Regression cases for bash-guard.sh: each line is "block|allow<TAB>command". Runs in CI (job "claude-guard").
# Add a case for every false positive or miss you find.
set -u
hook="$(dirname "$0")/bash-guard.sh"
pass=0
fail=0

while IFS=$'\t' read -r want cmd; do
  [ -z "$want" ] && continue
  jq -n --arg c "$cmd" '{tool_input: {command: $c}}' | bash "$hook" 2>/dev/null
  if [ $? -eq 2 ]; then got=block; else got=allow; fi
  if [ "$got" = "$want" ]; then
    pass=$((pass + 1))
  else
    fail=$((fail + 1))
    echo "FAIL: expected $want, got $got: $cmd"
  fi
done <<'CASES'
block	rm -rf var/cache
block	rm -fr ./build
block	rm -rf "my dir"
block	ls && rm -rf /tmp/x
block	git push origin main
block	git push origin "master"
block	git commit -m wip --no-verify
block	git clean -fd
block	git stash -u
allow	rm -r var/cache/dev
allow	git push -u origin feat/x && gh pr merge 3 --base main
allow	git push -u origin feature/maintenance
allow	make quality
allow	echo "rm -rf is dangerous"
allow	git commit -m "fix: never use rm -rf in scripts"
CASES

echo "bash-guard: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
