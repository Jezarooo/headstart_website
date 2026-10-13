# HeadStart AgentOps Directives

<CRITICAL_CONSTRAINTS>
1. [GATE_1_INITIALIZATION] 
   - RULE: You MUST halt and ask the user for the "Holy Trinity" of Live Files (PHP snippets, Custom JS, and headstart-global.css) (via link or zip in `Site-Scripts/Latest-Drop/`) at the start of any new feature. 
   - RULE: Unpack these into a new `Site-Scripts/live-site-backups-YYYY-MM-DD/` folder.
   - RULE: Copy required files to working directories. Do NOT edit reference files.

2. [GATE_2_PLANNING]
   - RULE: Write `plan.md`. Include a "Testing Strategy" (Automated + UAT). 
   - RULE: Do NOT write code. Await user literal string "APPROVED".

3. [GATE_3_EXECUTION]
   - RULE: Upon approval, run `touch .gemini/SPRINT_UNLOCKED`. 
   - RULE: While this file exists, you are in Sprint Mode. You may freely debug, test, and edit code without asking for permission for every change.

4. [GATE_4_TESTING]
   - RULE: Run syntax linting (e.g. `php -l`) on modified files.
   - RULE: Hit local endpoints/headless to verify 200 OK (no fatal errors).

5. [GATE_5_UAT]
   - RULE: If visual/UI changes occurred, halt and present a UAT Checklist to the user. Await "UAT Passed".

6. [GATE_6_HANDOVER]
   - RULE: Update `DEV-BRIEF.md`.
   - RULE: Zip files into `Site-Scripts/Handover/<feature>/<feature>.zip`. (Zip sits INSIDE the feature folder).
   - RULE: Commit changes to Git (`git add . && git commit -m "..."`).
   - RULE: Delete the lock file: `rm .gemini/SPRINT_UNLOCKED`.
</CRITICAL_CONSTRAINTS>
