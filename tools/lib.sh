# HeadStart workflow — shared rules. Sourced by tools/hs and tools/git-hooks/*.
# Must stay compatible with macOS bash 3.2 (no assoc arrays, mapfile, ${x,,}, etc).

HS_CODE_RE='^(Site-Scripts/(php|js|css)/|app/public/wp-content/mu-plugins/)'
HS_BACKUP_RE='^Site-Scripts/live-site-backups-'
HS_PROTECTED_RE='^(work/[^/]+/(APPROVAL|UAT)|DEPLOY-LOG\.md)$'
HS_BRANCH_RE='^(feat|fix)/[a-z0-9][a-z0-9-]*$'
HS_LOCAL_PHP_GLOB='/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-*/bin/darwin-arm64/bin/php'
HS_STALE_DAYS=7
HS_JS_MANIFEST='Site-Scripts/js/MANIFEST.tsv'
HS_OUTBOX='.hs-outbox'

hs_die()  { printf '\n✖ %s\n\n' "$*" >&2; exit 1; }
hs_ok()   { printf '✔ %s\n' "$*"; }
hs_warn() { printf '! %s\n' "$*" >&2; }
hs_info() { printf '  %s\n' "$*"; }

hs_branch() { git symbolic-ref --short -q HEAD 2>/dev/null || echo DETACHED; }
hs_slug() {
  local b; b=$(hs_branch)
  case "$b" in feat/*|fix/*) echo "${b#*/}" ;; *) echo "" ;; esac
}
hs_workdir() { local s; s=$(hs_slug); [ -n "$s" ] && echo "work/$s"; }
hs_sha() { shasum -a 256 "$1" | awk '{print $1}'; }
hs_now() { date +%Y-%m-%dT%H:%M:%S%z; }
hs_today() { date +%Y-%m-%d; }
hs_clean() { [ -z "$(git status --porcelain)" ]; }

hs_find_php() {
  [ -n "${HS_NO_PHP:-}" ] && { echo ""; return; }   # test hook: simulate a machine without PHP
  if command -v php >/dev/null 2>&1; then command -v php; return; fi
  local p
  for p in $HS_LOCAL_PHP_GLOB; do [ -x "$p" ] && { echo "$p"; return; }; done
  echo ""
}

# Plan approval is valid only while plan.md is byte-identical to what was approved.
hs_approved() {
  local wd want
  wd=$(hs_workdir); [ -n "$wd" ] || return 1
  [ -f "$wd/APPROVAL" ] && [ -f "$wd/plan.md" ] || return 1
  want=$(sed -n 's/^plan_sha256: //p' "$wd/APPROVAL")
  [ -n "$want" ] && [ "$want" = "$(hs_sha "$wd/plan.md")" ]
}

hs_newest_backup() { ls -d Site-Scripts/live-site-backups-* 2>/dev/null | sort | tail -1; }

# Lint receipts: content-addressed (git blob sha), kept in .git so they are never committed.
hs_receipts() { echo "$(git rev-parse --absolute-git-dir)/hs-lint-receipts"; }
hs_has_receipt() { grep -q "^$1 " "$(hs_receipts)" 2>/dev/null; }
hs_add_receipt() { printf '%s %s %s\n' "$1" "$2" "$3" >> "$(hs_receipts)"; }

# hs_lint_php <php-bin> <file-to-lint> <display-path>  -> 0 ok / 1 error (prints error)
hs_lint_php() {
  local out
  if out=$("$1" -l "$2" 2>&1); then return 0; fi
  printf '%s\n' "$out" | grep -v '^Errors parsing' | sed "s#$2#$3#g" >&2
  return 1
}

# Read one top-level-ish field from hook JSON on stdin-captured var. jq -> node -> sed fallback.
hs_json_get() { # $1 json, $2 jq path like .tool_input.command
  if command -v jq >/dev/null 2>&1; then
    printf '%s' "$1" | jq -r "$2 // empty" 2>/dev/null; return
  fi
  if command -v node >/dev/null 2>&1; then
    printf '%s' "$1" | node -e '
      let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{let o=JSON.parse(s);
      for(const k of process.argv[1].split(".").filter(Boolean)){o=o==null?undefined:o[k]}
      if(o!==undefined&&o!==null)process.stdout.write(String(o))}catch(e){}})' "$2"; return
  fi
  local key="${2##*.}"
  printf '%s' "$1" | sed -n "s/.*\"$key\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" | head -1
}

# JS manifest: working (clean) name <-> live file name in uploads/hs-head/.
hs_live_name() { # repo path -> the file's name on live
  local b h; b=$(basename "$1")
  case "$1" in
    Site-Scripts/js/*.js)
      h=$(awk -F'\t' -v c="$b" '$1==c{print $2; exit}' "$HS_JS_MANIFEST" 2>/dev/null)
      [ -n "$h" ] && { echo "$h"; return; } ;;
  esac
  echo "$b"
}
hs_clean_name() { # live file name -> working name (unchanged if not in the manifest)
  local c; c=$(awk -F'\t' -v x="$1" '$2==x{print $1; exit}' "$HS_JS_MANIFEST" 2>/dev/null)
  echo "${c:-$1}"
}
hs_live_location() { # repo path -> where it goes on live, for DEV-NOTES
  case "$1" in
    Site-Scripts/js/*) echo "wp-content/uploads/hs-head/$(hs_live_name "$1")" ;;
    Site-Scripts/php/*) echo "Code Snippets: $(basename "$1" .code-snippets.php)" ;;
    Site-Scripts/css/*) echo "Global stylesheet ($(basename "$1"))" ;;
    app/public/wp-content/mu-plugins/*) echo "wp-content/mu-plugins/$(basename "$1")" ;;
    *) echo "$1" ;;
  esac
}

# --- Race-proof commits (roadmap git-index-race, 14 Oct 2026) -------------------------------
# On the Mac's shared folder (cloud session) git's index has been found empty mid-commit, and
# git's automatic maintenance ran at the same instant. hs therefore commits with a private index
# outside the repo, with auto-maintenance off, and checks every commit it makes.
hs_git_safe_begin() {
  [ -n "${HS_SAFE_INDEX:-}" ] && return 0
  export GIT_CONFIG_COUNT=2 GIT_CONFIG_KEY_0=maintenance.auto GIT_CONFIG_VALUE_0=false \
         GIT_CONFIG_KEY_1=gc.auto GIT_CONFIG_VALUE_1=0
  HS_SAFE_INDEX="${TMPDIR:-/tmp}/hs-index.$$"
  rm -f "$HS_SAFE_INDEX"
  GIT_INDEX_FILE="$HS_SAFE_INDEX" command git read-tree HEAD || hs_die "Could not prepare a private git index."
  export GIT_INDEX_FILE="$HS_SAFE_INDEX" HS_SAFE_INDEX
  trap hs_git_safe_end EXIT
}
hs_git_safe_end() {
  local rc=$?
  if [ -n "${HS_SAFE_INDEX:-}" ]; then
    unset GIT_INDEX_FILE
    rm -f "$HS_SAFE_INDEX"
    command git reset -q 2>/dev/null || true   # real index := HEAD; working tree untouched
    HS_SAFE_INDEX=
  fi
  return $rc
}
# After a commit/merge: undo it (soft) if it is empty or deletes more than 10 files unasked.
hs_verify_commit() {
  local n del
  n=$(command git ls-tree -r --name-only HEAD | wc -l | tr -d ' ')
  del=0
  if command git rev-parse -q --verify HEAD~1 >/dev/null; then
    del=$(command git diff --name-only --diff-filter=D HEAD~1 HEAD | wc -l | tr -d ' ')
  fi
  if [ "$n" -eq 0 ] || { [ "$del" -gt 10 ] && [ -z "${HS_ALLOW_BULK_DELETE:-}" ]; }; then
    command git reset -q --soft HEAD~1 2>/dev/null || true
    hs_die "Safety check: that commit would have left $n files and deleted $del. It has been undone (your files are untouched). This usually means git's index was emptied mid-commit; run tools/hs status, then retry. Tell Jeremy/Claude if it repeats."
  fi
}
