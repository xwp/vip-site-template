# Slack Notifications

This document shows examples of Slack notifications sent by our GitHub Actions workflows.

## Configuration

Slack notifications are **optional** and require both:

- `SLACK_CHANNEL` repository variable (e.g., `#releases`)
- `SLACK_WEBHOOK_URL` repository secret

If either is missing, workflows run silently without notifications.

## Setup

To enable Slack notifications, you need to create a webhook URL:

### Step 1: Create a Slack App

1. Go to [Slack API Apps](https://api.slack.com/apps)
2. Click "Create New App" → "From scratch"
3. Enter app name (e.g., "Client X Deployments") and select your workspace
4. Click "Create App"

### Step 2: Enable Incoming Webhooks

1. In your app settings, go to "Incoming Webhooks"
2. Toggle "Activate Incoming Webhooks" to **On**
3. Click "Add New Webhook to Workspace"
4. Select the channel where notifications should be sent (e.g., `#releases`)
5. Click "Allow"

### Step 3: Copy Webhook URL

1. Copy the webhook URL (starts with `https://hooks.slack.com/services/...`)
2. In your GitHub repository, go to Settings → Secrets and variables → Actions
3. Click "New repository secret"
4. Name: `SLACK_WEBHOOK_URL`
5. Value: Paste the webhook URL
6. Click "Add secret"

### Step 4: Set Channel Variable

1. In the same GitHub settings page, click the "Variables" tab
2. Click "New repository variable"
3. Name: `SLACK_CHANNEL`
4. Value: Channel name (e.g., `#releases`)
5. Click "Add variable"

### Testing the Setup

- Push a commit to any protected branch or create a PR
- Check the configured Slack channel for notifications
- If no notifications appear, verify both `SLACK_CHANNEL` and `SLACK_WEBHOOK_URL` are set correctly

## Notification Examples

### 1. Test Results (test-deploy.yml)

**Tests Passed:**
```
Tests Passed

Branch: main
Event: Pull Request
Commit: abc123de
Author: john-doe
Will Deploy: No - none

[View Run]
```

**Tests Failed:**
```
Tests Failed

Branch: feature/new-feature
Event: Pull Request  
Commit: def456gh
Author: jane-smith
Will Deploy: No - none

[View Run]
```

### 2. Deployment Started (test-deploy.yml)

```
Deployment Started

Environment: production
Branch: production
Commit: ghi789jk
Deployed by: release-manager
```

### 3. Deployment Success (test-deploy.yml)

```
Deployment Successful

Environment: production
Branch: production
Commit: ghi789jk
Deployed by: release-manager
Deployment Time: ~3 minutes

[View Environment] [View Deployment]
```

### 4. Deployment Failed (test-deploy.yml)

```
Deployment Failed

Environment: staging
Branch: main
Commit: mno012pq
Deployed by: developer
Action Required: Immediate attention needed - Check logs and consider rollback if necessary

[View Logs] [Rollback Guide]
```

### 5. Release Branch Created (create-release-branch.yml)

```
Release Branch Created

Version: v2.1.0
Branch: release
Created by: tech-lead
Base Commit: stu345vw
Last commit message here
Next Steps:
• Deploy to UAT environment
• Run UAT testing  
• Create production PR when ready

[View Release Branch] [View Draft Release] [Deploy to UAT]
```

### 6. Release Branch Creation Failed (create-release-branch.yml)

```
Release Branch Creation Failed

Version: v2.1.0
Triggered by: tech-lead
Error: Check the workflow logs for details

[View Logs]
```

### 7. Production PR Created (create-production-pr.yml)

```
Production Release PR Created

PR Number: #42
Created by: tech-lead
Commits: 8 commits ready for production
Hotfix Release: false
Release Notes: Bug fixes and performance improvements
Action Required: @xwp/client-x: Please review and approve this PR for production deployment

[Review PR] [View Changes]
```

### 8. Hotfix Production PR Created (create-production-pr.yml)

```
HOTFIX Production Release PR Created

PR Number: #43
Created by: tech-lead
Commits: 2 commits ready for production
Hotfix Release: true
Release Notes: Critical security fix for user authentication
Action Required: @xwp/client-x: Please review and approve this PR for production deployment

[Review PR] [View Changes]
```

### 9. Production PR Creation Failed (create-production-pr.yml)

```
Production PR Creation Failed

Triggered by: tech-lead
Hotfix Release: false
Error: Check the workflow logs for details

[View Logs]
```

### 10. Production Release Completed (cleanup-release-branch.yml)

```
Production Release Completed & Cleaned Up

Version: v2.1.0
Deployed by: tech-lead
PR: #42
Branch Cleanup: Release branch deleted
Actions Completed:
• Production deployment successful
• Release tag created: v2.1.0
• GitHub release published
• Release branch cleaned up

[View Production] [View Release] [View Deployment]
```

### 11. Release Cleanup Partial Success (cleanup-release-branch.yml)

```
Production Release Completed (Cleanup Skipped)

Version: v2.1.0
Deployed by: tech-lead
Note: Release branch was already deleted (likely by GitHub's auto-delete feature)
```

### 12. Release Cleanup Failed (cleanup-release-branch.yml)

```
Release Cleanup Failed

PR: #42
Deployed by: tech-lead
Error: Production deployment succeeded but cleanup failed. Check the workflow logs for details. Manual cleanup may be required.

[View Logs]
```

## Footer

All notifications include a footer showing:
```
XWP Deploy Bot
```
(or the custom name configured in `GIT_USER_NAME` variable)

## Notification Flow

### Notification Types by Environment

| Environment | Triggers | Notifications |
|-------------|----------|---------------|
| **Development** | Push to `develop` | Test Results → Deployment Started → Success/Failed |
| **Test/Staging** | Push to `main` | Test Results → Deployment Started → Success/Failed |
| **Pre-Production** | Push to `release` | Test Results → Deployment Started → Success/Failed |
| **Production** | Push to `production` | Test Results → Deployment Started → Success/Failed → Release Completed |

### Manual Workflow Notifications

| Workflow | Trigger | Success | Failure |
|----------|---------|---------|---------|
| **Create Release** | Manual action | Release Branch Created | Release Creation Failed |
| **Create Production PR** | Manual action | Production PR Created | Production PR Failed |
| **Release Cleanup** | Auto after merge | Production Release Completed | Release Cleanup Failed |
