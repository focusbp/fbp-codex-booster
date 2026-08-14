# Codex token efficiency benchmark: contact management

## Purpose

Compare the token usage, prompt-cache reuse, implementation time, repair count, and completed quality of the following two approaches:

1. FBP with the Git-managed FBP Skills and local development rules.
2. A new repository without FBP, Skills, plugins, MCP configuration, or project instructions.

The benchmark application is a Japanese public contact form with authenticated inquiry management.

## Fixed acceptance scope

- Public form fields: name, email address, subject, inquiry body.
- Required and email validation, confirmation, completion, CSRF protection, escaping, and duplicate prevention.
- Authenticated management list, keyword search, three-status filtering, detail, status and memo update, and confirmed deletion.
- Desktop and mobile browser verification.
- No paid or externally hosted runtime service.

Both approaches receive the same functional requirements. A separate reviewer applies the same black-box acceptance checks. Failed checks are returned to the original generation thread, with at most five functional repair turns.

## Measurement

Record these values separately for bootstrap, initial generation, infrastructure retries, functional repairs, and independent review:

- elapsed time
- input tokens
- cached input tokens
- cache-write input tokens
- output and reasoning-output tokens
- repair turns
- generated application and test size
- acceptance results

Cached-input ratio and total input are both reported because cached input can reduce processing cost while still contributing to context and rate limits.

## Plain-repository result

- The initial PHP and SQLite implementation could not execute its functional suite because the assigned PHP runtime lacked SQLite support.
- One functional repair replaced it with a Python standard-library WSGI and SQLite implementation.
- The generator's five test groups passed, followed by an independent rerun and real-browser verification of submission, authentication, search, detail, update, deletion, and mobile layout.
- Including one read-only resume infrastructure retry: input 2,091,042; cached input 1,968,896; output 53,857; reasoning output 3,792.
- Excluding that infrastructure retry: input 1,587,710; cached input 1,498,624; output 40,329; reasoning output 2,763.
- Final implementation, CSS, tests, and setup guide totaled 321 lines and 33,679 bytes.

## FBP run

- The initial attempted appcode was unavailable because project registration had not been completed.
- The benchmark was restarted with the registered `app-compare` appcode.
- The standard project bootstrap completed successfully in 42.69 seconds.
- Initial feature generation used input 3,195,352; cached input 3,055,872; output 20,400; reasoning output 3,213; elapsed 609.54 seconds.
- Independent browser review found one functional defect: replacing the full public page after confirmation detached FBP's delegated Ajax handler, so the submit button no longer worked.
- One repair changed the confirm, back, and completion transitions to replace only the public content area. The repair used input 4,572,612; cached input 4,407,040; output 26,018; reasoning output 4,785; elapsed 148.36 seconds.
- Total feature work used input 7,767,964; cached input 7,462,912; output 46,418; reasoning output 7,998. The cached-input ratio was 96.07%, while uncached input was 305,052 tokens.
- The final generated source, templates, project docs, and verification script totaled 377 lines and 28,389 bytes.
- Generator checks and independent browser checks passed after repair: public confirmation and submission, authenticated management visibility, mobile layout, schema, cross-field search implementation, status filtering, CSRF rejection, escaping, update route, and confirmed deletion route. Review submissions were removed afterward.

## Pilot comparison

- Both variants required one functional repair.
- Excluding the plain repository's read-only retry, the plain variant used input 1,587,710 with cached input 1,498,624. The FBP variant used 4.89 times as much raw input and 3.42 times as much uncached input.
- Cached-input reuse worked in both environments: 96.07% for FBP and 94.39% for plain. FBP therefore uses the prompt cache effectively, but this run does not show token reduction because its much larger repeated context outweighed the higher cache ratio.
- Feature-generation elapsed time was 757.90 seconds for FBP and 608.40 seconds for plain, excluding bootstrap and the plain infrastructure retry. FBP was 24.6% slower in this pilot.
- FBP avoided designing authentication, persistence, and administration infrastructure from scratch, but the generator spent substantial context on Skill files, framework searches, and verbose CLI output. The framework advantage appeared in conventions and reproducible checks rather than token economy.
- The practical optimization target is to reduce Skill routing and tool-output volume: load only the minimum relevant references, add concise task-specific examples, suppress secrets and full settings/schema dumps, and expose compact CLI verification output.
- This single run does not establish a general result. Repeat the same acceptance suite several times and compare medians, ideally with fresh projects but a stable prompt prefix.

## Interpretation policy

- Do not treat unexecuted checks as passed.
- Report environment or orchestration failures separately from application defects, but include their actual token and elapsed-time cost in the operational total.
- Do not infer that cached input reduces the raw context size; compare raw tokens, cached tokens, and effective cost separately.
- A single run is a pilot. Repeat runs and medians are required before generalizing the result.
