# GitHub Actions Workflows

This repository uses automated GitHub Actions workflows to handle testing, deployments, and release management.

## Overview

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| [test-deploy.yml](test-deploy.yml) | PR/Push | Lint, test, and deploy to environments |
| [create-release-branch.yml](create-release-branch.yml) | Manual | Create release branch from main |
| [create-production-pr.yml](create-production-pr.yml) | Manual | Create production release PR |
| [cleanup-release-branch.yml](cleanup-release-branch.yml) | Production merge | Update release, cleanup branch |
| [docker-images.yml](docker-images.yml) | Docker changes | Build/publish Docker images |

## Release Management Workflows

### 1. Create Release Branch

**File**: `create-release-branch.yml`  
**Trigger**: Manual (workflow_dispatch)  
**Purpose**: Creates a `release` branch from `main` for UAT testing

**Usage**:

1. Go to Actions → "Create Release Branch"
2. Optionally check "Force update existing branch" if needed
3. Click "Run workflow" (version will be auto-generated)

**What it does**:

- Creates/updates `release` branch from latest `main`
- Generates changelog with recent commits
- Creates GitHub release draft with changelog
- Automatically triggers deployment to pre-prod environment
- Sends Slack notification (if configured)

### 2. Reset Branch

**File**: `reset-branch.yml`  
**Trigger**: Manual (workflow_dispatch)  
**Purpose**: Reset a target branch to match a source branch (destructive operation)

**Usage**:

1. Go to Actions → "Reset Branch"
2. Select **From** branch (main or production)
3. Select **To** branch (develop)
4. Type "RESET" to confirm the destructive operation
5. Click "Run workflow"

**What it does**:

- Validates the confirmation input
- Resets target branch to exactly match source branch
- Force pushes the updated branch
- Automatically triggers Test and Deploy workflow
- Sends Slack notifications for start/success/failure

**⚠️ Warning**: This is a destructive operation that will overwrite the target branch completely.

### 3. Create Production PR  

**File**: `create-production-pr.yml`  
**Trigger**: Manual (workflow_dispatch)  
**Purpose**: Creates PR from `release` → `production` with comprehensive checklist

**Usage**:

1. Go to Actions → "Create Production Release PR"
2. Enter release notes
3. Click "Run workflow"

**What it does**:

- Validates `release` branch exists
- Generates changelog from commits
- Creates PR with deployment checklist
- Assigns `@xwp/client-x` team as reviewers
- Adds `production-release` label

### 3. Release Cleanup

**File**: `cleanup-release-branch.yml`  
**Trigger**: Automatic (when production PR is merged)  
**Purpose**: Cleans up after successful production deployment and syncs branches

**What it does**:

- Deletes release branch (if exists)
- Publishes GitHub release with cross-linking to production PR
- Sends completion notification

## Test & Deploy Workflow

**File**: `test-deploy.yml`  
**Trigger**: All PRs and pushes to protected branches

### Job Topology

Lint and Test run as **parallel jobs** on separate runners, cutting wall-clock time roughly in half compared to serial execution. A lightweight Notify job sends a single consolidated Slack message after both complete. Deploy runs only on pushes to protected branches.

```txt
  ┌──────┐  ┌──────┐
  │ Lint │  │ Test │   ← parallel
  └──┬───┘  └──┬───┘
     │   ┌─────┘
  ┌──▼───▼──────┐
  │ Notify Slack │   ← consolidated result
  ├──────────────┤
  │    Deploy    │   ← protected branches only
  └──────────────┘
```

### For Pull Requests

- ✅ Lint and Test (parallel)
- ❌ No deployment

### For Branch Pushes

- ✅ Lint and Test (parallel)
- ⏭️ Tests skipped for `release`/`production` (already tested upstream)
- 🚀 Deploy to environment:
  - `develop` → Dev environment
  - `main` → Test environment
  - `release` → Pre-prod environment
  - `production` → Production environment

### Features

- **Parallel jobs** — Lint and Test run simultaneously on separate runners
- **No Docker in Lint** — Lint job skips Docker login/pull for faster setup
- **Incremental PHPCS on PRs** — only changed PHP files are checked; full scan on pushes to protected branches
- **Consolidated Slack** — single notification after both jobs complete
- Auto-cancellation of redundant runs
- NewRelic deployment markers (production only)

### Branch Protection

If branch protection rules reference the old **"Lint and Test"** check name, update them to require both **"Lint"** and **"Test"**. The **"Notify Slack"** job should not be a required check.

## Docker Image Management

**File**: `docker-images.yml`  
**Purpose**: Builds and publishes Docker images when needed

### Triggers

- Docker-related file changes (builds only, doesn't publish)
- PR labeled with `docker-image-build` (builds and publishes)
- Manual workflow dispatch (builds and publishes)

### Usage

1. Make Docker changes (Dockerfile, docker-compose.yml)
2. Push to PR
3. Add `docker-image-build` label to PR
4. Images are built and published to GitHub Container Registry

### Benefits

- Only builds when actually needed (saves CI time)
- Explicit control over publishing
- Version immutability

## Configuration

### Required GitHub Secrets

```bash
DEPLOY_SSH_KEY          # SSH key for VIP deployments
```

### Optional GitHub Secrets

```bash
SLACK_WEBHOOK_URL       # Slack webhook for notifications
NEW_RELIC_API_KEY      # NewRelic deployment markers
```

📋 **Slack setup guide**: [SLACK-NOTIFICATIONS.md](SLACK-NOTIFICATIONS.md#setup)

### Optional GitHub Variables

```bash
SLACK_CHANNEL          # Slack channel (e.g., #releases)
GIT_USER_NAME          # Git author name (default: XWP Deploy Bot)
GIT_USER_EMAIL         # Git author email (default: technology@xwp.co)
```

### Required GitHub Labels

```bash
production-release     # Added to production PRs
docker-image-build     # Triggers Docker image publishing
```

## Code Ownership

The [CODEOWNERS](../CODEOWNERS) file automatically assigns reviewers:

- All files require review from `@xwp/client-x` team
- Works with branch protection rules to enforce reviews

## Slack Notifications

All workflows support optional Slack notifications. Examples and setup details: [SLACK-NOTIFICATIONS.md](SLACK-NOTIFICATIONS.md)

## Best Practices

### For Developers

- Create feature branches from `main`
- Ensure tests pass before merging
- Use descriptive commit messages
- Add Docker label only when publishing images

### For EM/TL

- Use release workflows for UAT and production
- Review production PRs carefully
- Monitor Slack notifications for deployment status
- Verify environment deployments before promoting
- Configure secrets and variables in repository settings
- Set up branch protection rules
- Keep Docker images updated

## Troubleshooting

### Common Issues

**Workflow fails with missing secrets**:

- Check repository secrets are configured
- Verify secret names match workflow expectations

**Docker builds fail**:

- Ensure `docker-image-build` label is added to PR
- Check Docker image versions in docker-compose.yml

**Deployments fail**:

- Verify SSH key has proper permissions
- Check VIP repository access
- Review deployment logs for specific errors

**Slack notifications not working**:

- Confirm both `SLACK_CHANNEL` and `SLACK_WEBHOOK_URL` are set
- Test webhook URL manually
- Check channel permissions

### Getting Help

1. Check workflow logs in GitHub Actions tab
2. Review error messages in PR checks
3. Consult team Slack channels
4. Contact EM/TL for infrastructure issues
