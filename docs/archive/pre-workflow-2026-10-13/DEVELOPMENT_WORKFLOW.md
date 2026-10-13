# HeadStart Standard Development Workflow (AgentOps)

This document outlines the strict AgentOps pipeline for making code updates to ensure immutable safety, rigorous testing, and smooth handovers.

## Pipeline Gates
1. **Initialization:** User drops latest live files into `Site-Scripts/Latest-Drop/`. Agent organizes them into read-only `Site-Scripts/live-site-backups-[DATE]/` folders.
2. **Immutability:** Agent hooks block any modifications to the backup folders. Files must be copied to working directories (`wp-content/themes/...`) before editing.
3. **Planning:** Agent writes `plan.md` detailing architecture and Testing Strategy.
4. **Execution (Sprint Unlocked):** Once the user approves the plan, the Agent generates `.gemini/SPRINT_UNLOCKED`. The agent may now rapidly code, test, and debug freely.
5. **Automated Testing:** Agent runs syntax linters (`php -l`) and headless HTTP checks to verify zero fatal errors.
6. **User Acceptance Testing (UAT):** Agent halts and provides a visual checklist for the user to approve UI changes.
7. **Handover:** 
   - Agent updates `DEV-BRIEF.md`.
   - Agent creates a zip package placed **inside** the dedicated handover folder (e.g., `Site-Scripts/Handover/feature/feature.zip`).
   - Agent commits all work to Git.
   - Agent deletes `.gemini/SPRINT_UNLOCKED`, triggering the Stop Hook to allow task completion.
