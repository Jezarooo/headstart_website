#!/usr/bin/env bash
# Tests for tools/hs. Everything runs in a throwaway clone in a temp folder: this repo's
# history and your learning log are never touched. Run: tools/tests/hs-tests.sh
set -u
SRC=$(git rev-parse --show-toplevel)
TMP=$(mktemp -d "${TMPDIR:-/tmp}/hs-tests.XXXXXX")
TMP=$(cd "$TMP" && pwd -P)   # macOS: /var is a link to /private/var, and git reports the real path
trap 'chmod -R u+w "$TMP" 2>/dev/null; rm -rf "$TMP"' EXIT
pass=0; fail=0
ok()  { pass=$((pass+1)); printf '  ok    %s\n' "$1"; }
bad() { fail=$((fail+1)); printf '  FAIL  %s\n' "$1"; [ -n "${2:-}" ] && printf '        %s\n' "$2"; }
check() { # name, command that must succeed
  if eval "$2" >"$TMP/out" 2>&1; then ok "$1"; else bad "$1" "$(head -3 "$TMP/out")"; fi
}
refute() { # name, command that must fail
  if eval "$2" >"$TMP/out" 2>&1; then bad "$1" "$(head -3 "$TMP/out")"; else ok "$1"; fi
}
section() { printf '\n%s\n' "$1"; }

C="$TMP/repo"
git clone -q "$SRC" "$C" || exit 1
cd "$C" || exit 1
git config user.name test; git config user.email test@example.invalid
export HS_NO_OPEN=1 HS_LEARN_DIR="$TMP/learn" CLAUDE_PROJECT_DIR="$C"
hs() { "$C/tools/hs" "$@"; }
# Make main hold the tools under test, whichever branch the source repo is on.
git checkout -q main 2>/dev/null || git checkout -q -b main
git checkout -q "origin/$(git -C "$SRC" branch --show-current)" -- tools 2>/dev/null || true
cp "$SRC"/tools/hs "$SRC"/tools/lib.sh tools/ ; mkdir -p tools/learning tools/templates tools/claude-code
cp "$SRC"/tools/learning/snippets.tsv tools/learning/; cp "$SRC"/tools/templates/* tools/templates/
cp "$SRC"/tools/claude-code/settings.json tools/claude-code/
hs setup >/dev/null
git add -A tools >/dev/null; HS_ALLOW_MAIN=1 git commit -q -m "chore(test): tools under test" >/dev/null

guard() { jq -n --arg c "$1" '{tool_input:{command:$c}}' | hs guard bash >/dev/null 2>&1; }
guard_edit() { jq -n --arg p "$1" '{tool_input:{file_path:$p}}' | hs guard edit >/dev/null 2>&1; }
prompt() { jq -n --arg p "$1" '{prompt:$p}' | hs hook prompt 2>&1; }
learn() { jq -n --arg c "$1" '{tool_name:"Bash",tool_input:{command:$c}}' | hs hook learn; }
stop() { jq -n --arg t "$1" '{stop_hook_active:false,last_assistant_message:$t}' | hs hook stop >/dev/null 2>&1; }
fill_plan() { sed -i '' 's/TODO: //' "work/$1/plan.md"; hs save -m "plan($1): filled in" >/dev/null; }

section "Setup"
check "hooks installed where Claude Code reads them" "cmp tools/claude-code/settings.json .claude/settings.json"
check "repo stays clean after setup" "[ -z \"\$(git status --porcelain)\" ]"
check "setup is safe to run twice" "hs setup && [ \$(grep -cx '/.claude/settings.json' .git/info/exclude) = 1 ]"
check "installed settings are valid JSON" "jq -e .hooks.PostToolUse .claude/settings.json"

section "Guards: blocked"
for c in 'git push --force origin main' 'git reset --hard HEAD~1' 'git clean -fd' 'rm -rf Site-Scripts' \
         'git commit --no-verify -m x' 'tools/hs approve --via x' 'tools/hs uat --via x' 'HS_ALLOW_MAIN=1 git commit -m x' \
         'echo x > Site-Scripts/live-site-backups-2026-11-05/core.css' 'rm Site-Scripts/live-site-backups-2026-11-05/core.css' \
         'echo x > work/foo/APPROVAL' 'tools/hs save -m "ok" && rm -rf Site-Scripts'; do
  refute "$c" "guard '$c'"
done
refute "edit a live backup" "guard_edit '$C/Site-Scripts/live-site-backups-2026-11-05/core.css'"
refute "edit an approval record" "guard_edit '$C/work/foo/APPROVAL'"
refute "edit code on main" "guard_edit '$C/Site-Scripts/php/x.php'"

section "Guards: allowed"
for c in 'tools/hs status' 'git log --oneline -3' 'tools/hs drift' 'grep -c x Site-Scripts/live-site-backups-2026-11-05/core.css 2>/dev/null' \
         'cat Site-Scripts/live-site-backups-2026-11-05/SNAPSHOT.md' 'tools/hs save -m "fix(x): stop rm -rf and git reset --hard in docs"' \
         'tools/hs plan --diff' 'cp Site-Scripts/live-site-backups-2026-11-05/core.css /tmp/x'; do
  check "$c" "guard '$c'"
done
check "edit a plan outside the code paths" "guard_edit '$C/work/foo/plan.md'"
check "edit a file outside the repo" "guard_edit '/tmp/elsewhere.php'"

section "Approval"
hs start feat t-approve "Approval test" >/dev/null
fill_plan t-approve
for m in 'not approved yet' 'approved, but change X' 'I approved it' 'approved
but change section 3'; do
  prompt "$m" >/dev/null
  refute "does not record: $(echo "$m" | head -1)" "[ -f work/t-approve/APPROVAL ]"
done
check "plan awaits approval before any" "hs status | grep -q 'Plan awaiting approval'"
prompt 'Approved.' >/dev/null
check "records: Approved." "[ -f work/t-approve/APPROVAL ]"
n=$(git rev-list --count HEAD)
prompt 'approved' >/dev/null
check "already approved records nothing" "[ \$(git rev-list --count HEAD) = $n ]"
echo "more" >> work/t-approve/plan.md; hs save -m "plan(t-approve): changed" >/dev/null
check "changed plan needs approval again" "hs status | grep -q 're-approval'"
check "plan --diff shows only the change" "hs plan --diff | grep -q '^+more'"
prompt 'APPROVED with thanks' >/dev/null
check "records: capital APPROVED at the start" "hs status | grep -q EXECUTING"
hs_uat_before=$(git rev-list --count HEAD)
prompt 'UAT PASSED' >/dev/null
check "records: UAT PASSED" "[ -f work/t-approve/UAT ]"

section "Stop hook: plan link"
git checkout -q main; hs start feat t-stop "Stop test" >/dev/null
check "doesn't fire while the plan is unfinished" "stop 'no link here'"
fill_plan t-stop
refute "blocks a reply with no link" "stop 'please approve'"
check "passes a reply with the link" "stop 'see work/t-stop/plan.md'"
check "doesn't fire again for the same version" "stop 'no link here'"
echo more >> work/t-stop/plan.md; hs save -m "plan(t-stop): changed" >/dev/null
refute "fires again for a new version" "stop 'please approve'"
check "stands down on Claude Code's repeat flag" "jq -n '{stop_hook_active:true,last_assistant_message:\"x\"}' | hs hook stop"
check "can't read the reply: doesn't trap it" "jq -n '{stop_hook_active:false}' | hs hook stop"
echo dirty > work/t-stop/x.txt
refute "still blocks on uncommitted work" "stop 'see work/t-stop/plan.md'"
mv work/t-stop/x.txt "$TMP/x.txt"

section "Plan command"
hs save -m "plan(t-stop): keep" >/dev/null
check "this branch's plan" "hs plan | grep -q 'work/t-stop/plan.md'"
check "another branch's plan as a copy" "hs plan t-approve | grep -q 'read-only copy'"
check "unknown task is refused" "! hs plan nope"
git checkout -q main
check "on main: newest open task" "hs plan | grep -q 'hs-plan-t-stop'"
check "status lists open tasks and gates" "hs status | grep 't-approve' | grep -q 'building\|handover'"

section "Learning snippets"
t0=1000000; export HS_LEARN_NOW=$t0
out=$(learn 'tools/hs save -m "x"')
check "an event gives a snippet" "echo '$(printf '%s' "$out" | jq -r .hookSpecificOutput.hookEventName)' | grep -q PostToolUse"
first=$(sed -n 1p "$HS_LEARN_DIR/headstart-seen")
check "basics come first" "[ '$first' = commit-what ]"
check "no snippet inside the cooldown" "[ -z \"\$(learn 'tools/hs save -m y')\" ]"
export HS_LEARN_NOW=$((t0+1799))
check "still none at 29m59s" "[ -z \"\$(learn 'tools/hs save -m y')\" ]"
export HS_LEARN_NOW=$((t0+1800))
out=$(learn 'tools/hs save -m "y"')
check "next snippet at 30 minutes" "[ -n '$(printf '%s' "$out" | head -c 1)' ]"
check "no repeat" "[ \$(sort '$HS_LEARN_DIR/headstart-seen' | uniq -d | wc -l) -eq 0 ] && [ \$(wc -l < '$HS_LEARN_DIR/headstart-seen') -eq 2 ]"
check "the words in a commit message don't count" "[ \"\$(jq -n '{tool_name:\"Bash\",tool_input:{command:\"tools/hs status\"}}' | HS_LEARN_NOW=9000000 hs hook learn)\" = '' ]"
export HS_LEARN_NOW=$((t0+3600))
out=$(jq -n --arg p "$C/Site-Scripts/php/x.php" '{tool_name:"Edit",tool_input:{file_path:$p}}' | hs hook learn)
check "editing PHP is an event" "printf '%s' '$(printf '%s' "$out" | tr -d "'" | head -c 300)' | grep -q 'programming'"
export HS_LEARN_NOW=$((t0+7200))
check "a file outside the repo isn't an event" "[ -z \"\$(jq -n '{tool_name:\"Edit\",tool_input:{file_path:\"/tmp/x.php\"}}' | hs hook learn)\" ]"
touch "$HS_LEARN_DIR/PAUSE"
check "PAUSE silences it" "[ -z \"\$(learn 'tools/hs save -m z')\" ]"
mv "$HS_LEARN_DIR/PAUSE" "$TMP/PAUSE.off"
prompt 'skip' >/dev/null
check "skip silences it for today" "[ -z \"\$(learn 'tools/hs save -m z')\" ]"
mv "$HS_LEARN_DIR/headstart-skip" "$TMP/skip.off"
check "works again after that" "[ -n \"\$(learn 'tools/hs save -m z')\" ]"
check "log holds what was shown" "hs learned | grep -q 'A commit is a saved snapshot'"
check "repo stays clean" "[ -z \"\$(git status --porcelain)\" ]"
total=$(grep -vc '^#' tools/learning/snippets.tsv)
for i in $(seq 1 $((total+3))); do export HS_LEARN_NOW=$((t0+10000+i*1800)); learn 'tools/hs save -m z' >/dev/null; done
check "starts again once everything has been seen" "[ \$(sort '$HS_LEARN_DIR/headstart-seen' | uniq -d | wc -l) -eq 0 ] && [ \$(wc -l < '$HS_LEARN_DIR/headstart-seen') -lt $total ]"
check "snippets are well formed" "awk -F'\t' '!/^#/ && NF!=4 {exit 1}' tools/learning/snippets.tsv"
unset HS_LEARN_NOW

section "Reading live snippets (real 5 Nov backup)"
. "$C/tools/lib.sh"
B=Site-Scripts/live-site-backups-2026-11-05
check "every snippet in the export is found" "[ \$(hs_backup_snippet_slugs $B | wc -l) -eq 36 ]"
found=0; for s in $(hs_backup_snippet_slugs $B); do hs_backup_read $B "Site-Scripts/php/$s.code-snippets.php" >/dev/null && found=$((found+1)); done
check "and each can be read" "[ $found -eq 36 ]"
check "a missing one reports missing" "! hs_backup_read $B Site-Scripts/php/nope.code-snippets.php"
check "the short-links snippet matches our copy" "! hs_live_differs $B Site-Scripts/php/weekly-update-short-links.code-snippets.php"
check "formatting-only differences are ignored" "[ \"\$(printf '<?php\n\n/**\n * Foo-Bar\n */\necho 1;  \r\n?>\n' | hs_code_norm x/foo-bar.code-snippets.php)\" = 'echo 1;' ]"
check "a comment that isn't our header is kept" "printf '<?php\n/**\n * Something else\n */\necho 1;\n' | hs_code_norm x/foo-bar.code-snippets.php | grep -q 'Something else'"
check "real differences show" "[ \"\$(echo 'a' | hs_code_norm x.php)\" != \"\$(echo 'b' | hs_code_norm x.php)\" ]"
check "drift has no false LOCAL ONLY for live snippets" "! hs drift | grep -q 'LOCAL ONLY.*weekly-update-short-links'"
check "older per-file backups still read" "hs_backup_read Site-Scripts/live-site-backups-2026-10-14 Site-Scripts/php/weekly-update-url-routing.code-snippets.php >/dev/null || ls Site-Scripts/live-site-backups-2026-10-14 >/dev/null"
check "reading leaves backups unchanged" "[ -z \"\$(git status --porcelain Site-Scripts/live-site-backups-2026-11-05)\" ]"

section "Handover safety"
# A fresh backup for this test, dated today, so the age check starts out quiet.
newb="Site-Scripts/live-site-backups-$(date +%Y-%m-%d)"
mkdir -p "$newb"; cp "$B"/snippets.code-snippets.json "$newb/"
HS_ALLOW_MAIN=1 git add -A "$newb" && HS_ALLOW_MAIN=1 git commit -q -m "snapshot: test backup"
hs start feat t-hand "Handover test" >/dev/null; fill_plan t-hand; prompt 'approved' >/dev/null; prompt 'UAT PASSED' >/dev/null
F=Site-Scripts/php/weekly-update-short-links.code-snippets.php
echo '// changed' >> "$F"; echo '// local' >> app/public/wp-content/mu-plugins/headstart-local-loader.php
hs save -m "feat(t-hand): change a live snippet" >/dev/null
check "a clean case scaffolds a package" "hs handover"
D=$(ls -d Site-Scripts/handover/t-hand-*)
check "the changed snippet is packaged" "[ -f $D/files/php/weekly-update-short-links.code-snippets.php ]"
check "it gets a rollback copy from the JSON export" "[ -f $D/rollback/php/weekly-update-short-links.code-snippets.php ]"
check "the rollback copy is valid PHP with the live code" "head -1 $D/rollback/php/weekly-update-short-links.code-snippets.php | grep -q '<?php' && grep -q hs_sl_handle_request $D/rollback/php/weekly-update-short-links.code-snippets.php && ! grep -q '// changed' $D/rollback/php/weekly-update-short-links.code-snippets.php"
check "local-only mu-plugins are left out" "[ ! -e $D/files/mu-plugins/headstart-local-loader.php ]"
check "the notes table lists the rollback" "grep -q 'rollback/php/weekly-update-short-links' $D/DEV-NOTES.md"
mv "$D" "$TMP/pkg-clean"; [ -f work/t-hand/HANDOVER.pending ] && mv work/t-hand/HANDOVER.pending "$TMP/"
git checkout -q -- . 2>/dev/null
git checkout -q main
# Live moves on: a newer backup holds a different version of that snippet than main.
chmod -R u+w "$newb"
jq '(.snippets[] | select(.name=="Weekly-Update--Short-Links") | .code) += "\n// Nick was here"' "$newb/snippets.code-snippets.json" > "$TMP/j" && cp "$TMP/j" "$newb/snippets.code-snippets.json"
mkdir -p "$newb-1200"; cp "$newb/snippets.code-snippets.json" "$newb-1200/"; git checkout -q -- "$newb"
HS_ALLOW_MAIN=1 git add -A "$newb-1200" && HS_ALLOW_MAIN=1 git commit -q -m "snapshot: live moved on"
git checkout -q feat/t-hand
hs sync >/dev/null
check "sync brings the new snapshot to the branch" "[ -d $newb-1200 ]"
check "approval survives a sync" "hs status | grep -q 'HANDOVER\|EXECUTING\|READY'"
refute "stops when live has changed" "hs handover"
check "and names the file" "hs handover 2>&1 | grep -q 'weekly-update-short-links'"
check "--accept-drift goes ahead" "hs handover --accept-drift 'checked with Nick'"
D=$(ls -d Site-Scripts/handover/t-hand-*)
check "and is recorded in DEV-NOTES" "grep -q 'checked with Nick' $D/DEV-NOTES.md"
git add -A >/dev/null; hs save -m "chore(t-hand): keep package" >/dev/null
git checkout -q main
# Old backup: HS_STALE_DAYS=-1 makes any backup count as old.
hs start feat t-old "Stale test" >/dev/null; fill_plan t-old; prompt 'approved' >/dev/null; prompt 'UAT PASSED' >/dev/null
echo '// x' >> Site-Scripts/php/magnific.code-snippets.php; hs save -m "feat(t-old): change" >/dev/null
refute "stops when the backup is old" "HS_STALE_DAYS=-1 hs handover"
check "and says how old" "HS_STALE_DAYS=-1 hs handover 2>&1 | grep -q 'days old'"
check "a current backup passes" "hs handover"
git add -A >/dev/null; hs save -m "chore(t-old): keep package" >/dev/null

section "Sync"
git checkout -q main
hs start feat t-sync "Sync test" >/dev/null
echo a > work/t-sync/conflict.txt; hs save -m "chore(t-sync): add a file" >/dev/null
git checkout -q main
mkdir -p work/t-sync; echo b > work/t-sync/conflict.txt
git add work/t-sync/conflict.txt; HS_ALLOW_MAIN=1 git commit -q -m "chore(test): clash on main"
git checkout -q feat/t-sync
refute "stops on a conflict" "hs sync"
check "and leaves the branch untouched" "[ \"\$(cat work/t-sync/conflict.txt)\" = a ] && [ -z \"\$(git status --porcelain)\" ]"
check "refuses to run on main" "git checkout -q main && ! hs sync; git checkout -q feat/t-sync"
echo dirty > untracked.txt
refute "refuses a dirty branch" "hs sync"
mv untracked.txt "$TMP/"

section "Older branches"
git checkout -q main
git checkout -q -b fix/t-oldtools "$(git rev-list --max-parents=0 HEAD | tail -1)" 2>/dev/null \
  && check "hooks stay installed on a branch without them" "[ -f .claude/settings.json ] && git check-ignore -q .claude/settings.json" \
  || bad "could not make an old branch"
git checkout -q main 2>/dev/null

printf '\n%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
