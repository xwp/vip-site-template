# Rules Overview

This folder defines how the AI assistant should behave for this WordPress site VIP codebase.  
All rules inherit the **global.mdc** baseline — project context, security, performance, and style guidance — and then add task-specific instructions.

---

## `global.mdc`

**Always applied.**  
Defines the **highest-priority rules** for this repository:

- Project context (site on WordPress VIP: multisite, high-traffic, auth-heavy).
- Core behavioral rules (read full files, no speculation, surgical edits).
- Security & VIP compliance (sanitize/escape/nonces, no disallowed APIs).
- Performance & scalability (avoid N+1, safe caching, async work).
- Multisite/auth handling, DB/query policy, caching/state rules.
- Evidence & output formatting (filename:line, ≤3-line snippets).
- Documentation, review, observability, testing, and process standards.

Every other rule builds on this.

---

## `rule-brainstorm.mdc`

For **architecture / solution ideation**.

- Used when exploring possible implementations, tools, or plugins.
- Output: 3–5 viable options with pros/cons, VIP notes, risks, and references.
- Leverages `global.mdc` to know about scale, security, performance.

---

## `rule-code-review.mdc`

For **code reviews** with bugs and edge cases as top priority.

- Rates issues **Critical / High / Medium / Low**.
- Requires file/line locations, short evidence snippets, why/fix/VIP ref.
- Adds a 1-page prioritized fix plan and optional PHPCS rule hints.

---

## `rule-document.mdc`

For **documenting existing code**.

- Adds PHPDoc/JSDoc comments without changing behavior.
- Generates a full `README.md` for the feature/block (overview, usage, security, performance, multisite, testing, etc.).
- Targets clarity for juniors and VIP compliance.

---

## `rule-feature-request.mdc`

For **implementing new features**.

- Creates clean, modular PHP/JS code with helpers/classes, hooks, and VIP-safe patterns.
- Enforces sanitization/escaping/nonces, multisite safety, and performance.
- Output: implemented code, short design rationale, usage notes, and sample tests.

---

## `rule-improve-security.mdc`

For **hardening existing code**.

- Reviews and rewrites to remove vulnerabilities.
- Enforces VIP security (sanitize/escape, nonces, caps, prepared queries, safe REST permissions).
- Adds inline `// CHANGED:` comments with security lessons.
- Notes VIP-specific changes if applied.

---

## `rule-refactor.mdc`

For **behavior-preserving refactors**.

- Improves clarity, structure, and performance without changing outputs or APIs.
- Keeps hooks/signatures stable; adds safe caching only if semantics unchanged.
- Output: diff-style summary, refactored code, optional equivalence note.

---

## `rule-jira-ticket.mdc`

For **writing Jira tickets** from a feature description.

- Converts a short feature note into a full ticket with:
  - User story,
  - Business value,
  - Testable acceptance criteria,
  - Dev notes (themes/plugins/hooks/caching/VIP),
  - QA/UAT notes,
  - Test steps.
- Asks for clarifications if acceptance criteria aren’t clear.

---

### How to Use

- **global.mdc** is always loaded — no need to duplicate its content elsewhere.
- Each other file is **task-specific**: use the one that matches your goal (brainstorm, refactor, review, etc.).
- Keep rule files focused; let `global.mdc` provide project context and shared constraints.
