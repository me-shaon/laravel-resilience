# Release Playbook

Use this playbook when a maintainer or AI agent is asked to cut a release tag for this package.

This package is ready for a first usable beta after Phase 5.

Recommended first beta tag:

- `v0.1.0-beta.1`

## Purpose

The goal of this playbook is to keep releases simple and repeatable:

- verify the repo is in a clean releasable state
- prepare release notes
- create and push the tag
- publish the GitHub release
- let the existing changelog workflow update `CHANGELOG.md`

## When To Use This

Use this playbook when the user says things like:

- create a release
- cut a tag
- publish `v0.x.y`
- prepare release notes for a beta or stable release

## Manual Release Guide

This section is for a developer or maintainer doing the release manually.

### Release Preconditions

Before tagging a release, confirm all of these:

1. The git working tree is clean.
2. The release branch is the intended branch.
3. The package checks pass:
   - `composer validate --no-check-publish`
   - `composer test`
   - `composer analyse`
   - `composer format -- --test`
4. The README matches the actual package publication status.
   If the package is now public, remove or update any wording that says it is unpublished.
5. The target version tag is confirmed by the user.

If any of these fail, stop and report the blocker before creating a tag.

### Standard Release Flow

1. Verify the repo is clean:

```bash
git status --short
```

2. Run release checks:

```bash
composer validate --no-check-publish
composer test
composer analyse
composer format -- --test
```

3. Review commits since the last tag:

```bash
git describe --tags --abbrev=0
git log LAST_TAG..HEAD --oneline
```

If there is no previous tag, use:

```bash
git log --oneline
```

4. Draft release notes using this structure:

```md
# vX.Y.Z

## Summary
- One short paragraph about what this release makes possible.

## Highlights
- Most important new capability
- Another user-facing improvement
- Any notable fix or compatibility improvement

## Verification
- composer validate --no-check-publish
- composer test
- composer analyse
- composer format -- --test

## Full Changelog
- short git log summary list
```

5. Create the tag:

```bash
git tag vX.Y.Z
```

6. Push the tag:

```bash
git push origin vX.Y.Z
```

7. Publish a GitHub release for that tag and paste in the prepared release notes.

8. After the GitHub release is published, wait for the existing changelog workflow to update `CHANGELOG.md`, then pull that commit back locally.

### Manual Release Checklist

Use this checklist before you consider the release complete:

1. All verification commands passed.
2. The release notes are written in user-facing language.
3. The correct tag was created and pushed.
4. The GitHub release was published from that tag.
5. The changelog workflow updated `CHANGELOG.md`.
6. The changelog commit was pulled back locally.

## AI Agent Instructions

This section is for an AI agent that is explicitly asked to create a release or prepare one.

When following this playbook, the agent should:

1. Never create or push a tag if the working tree is dirty.
2. Never skip the verification commands.
3. Summarize the commit range in user-facing language for the release notes.
4. Ask for approval before creating or pushing a tag if the user has not explicitly asked for that action yet.
5. Mention any remaining release risks clearly.

### Agent Workflow

When the user gives a target version such as `v0.1.0-beta.1`, the agent should:

1. Check the working tree with `git status --short`.
2. Run the full verification commands from this playbook.
3. Review the commits since the last tag.
4. Draft release notes using the structure in this playbook.
5. Stop and report any blockers before tagging.
6. If the user has explicitly asked to create the tag, create it.
7. If the user has explicitly asked to push and publish, push the tag and proceed with the GitHub release flow.
8. Remind the user to sync the changelog update commit after the release workflow runs.

### Agent Output Expectations

The agent should report:

- whether the release is ready or blocked
- the exact checks it ran
- any remaining manual decisions
- the proposed release notes body
- the exact tag it created or plans to create

## Beta Guidance

Suggested near-term versions:

- first beta after Phase 5: `v0.1.0-beta.1`
- additional beta iterations: `v0.1.0-beta.2`, `v0.1.0-beta.3`
- broader scenario-runner beta after Phase 6: `v0.2.0-beta.1`

## Current Beta Readiness

As of the Phase 5 implementation, the package already has:

- safety defaults and activation guards
- a rule-based fault model
- container fault injection
- Laravel-native helpers for HTTP, mail, cache, queue, and storage
- named target support for non-default drivers
- resilience assertion helpers

That makes it suitable for a first beta tag once the release notes and README publication wording are finalized.
