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
🟢 ✅ All Tests Passed
✅ Tests Passed Successfully

Branch: main
Event: Pull Request
Commit: abc123de
Author: john-doe
Will Deploy: No - none

[View Run]

🤖 XWP Deploy Bot
```

**Tests Failed:**
```
🔴 ❌ Test Suite Failed
❌ Tests Failed

Branch: feature/new-feature
Event: Pull Request  
Commit: def456gh
Author: jane-smith
Will Deploy: No - none

[View Run]

🤖 XWP Deploy Bot
```

### 2. Deployment Started (test-deploy.yml)

```
🟠 🚀 Deployment Started
🚀 Deployment In Progress

Environment: production
Branch: production
Commit: ghi789jk
Deployed by: release-manager

🤖 XWP Deploy Bot
```

### 3. Deployment Success (test-deploy.yml)

```
🟢 ✅ Deployment Successful
✅ Deployment Completed Successfully

Environment: production
Branch: production
Commit: ghi789jk
Deployed by: release-manager

[View Environment] [View Deployment]

🤖 XWP Deploy Bot
```

### 4. Deployment Failed (test-deploy.yml)

```
🔴 ❌ Deployment Failed
❌ Deployment Failed - Immediate Attention Required

Environment: staging
Branch: main
Commit: mno012pq
Deployed by: developer
Action Required: Immediate attention needed - Check logs and consider rollback if necessary

[View Logs] [Rollback Guide]

🤖 XWP Deploy Bot
```

### 5. Release Branch Created (create-release-branch.yml)

```
🟢 🔧 Release Branch Created
🔧 Release Branch Ready for Testing

Version: release-20240916-143022
Branch: release
Created by: tech-lead
Base Commit: stu345vw
Last commit message here
Next Steps:
• Deploy to UAT environment
• Run UAT testing  
• Create production PR when ready

[View Release Branch] [View Draft Release] [Deploy to UAT]

🤖 XWP Deploy Bot
```

### 6. Release Branch Creation Failed (create-release-branch.yml)

```
🔴 ❌ Release Branch Creation Failed
❌ Release Branch Creation Failed

Version: release-20240916-143022
Triggered by: tech-lead
Error: Check the workflow logs for details

[View Logs]

🤖 XWP Deploy Bot
```

### 7. Production PR Created (create-production-pr.yml)

```
🟠 📋 Production Release PR Created
📋 Production Release PR Ready

PR Number: #42
Created by: tech-lead
Commits: 8 commits ready for production
Release Notes: Bug fixes and performance improvements
Action Required: @xwp/client-x: Please review and approve this PR for production deployment

[Review PR] [View Changes]

🤖 XWP Deploy Bot
```

### 8. Production PR Creation Failed (create-production-pr.yml)

```
🔴 ❌ Production PR Creation Failed
❌ Production PR Creation Failed

Triggered by: tech-lead
Error: Check the workflow logs for details

[View Logs]

🤖 XWP Deploy Bot
```

### 9. Branch Reset Started (reset-branch.yml)

```
🟠 ⚠️ Branch Reset In Progress
🔄 Branch Reset Started

Operation: Reset `develop` to match `main`
Source Branch: `main`
Target Branch: `develop`
Initiated by: tech-lead
Warning: ⚠️ This is a destructive operation that will overwrite the target branch

[View Run]

🤖 XWP Deploy Bot
```

### 10. Branch Reset Successful (reset-branch.yml)

```
🟢 ✅ Branch Reset Completed Successfully
✅ Branch Reset Successful

Operation: Reset `develop` to match `main`
Source Branch: `main` (`f0337dfc`)
Target Branch: `develop` (`f0337dfc`)
Previous Target: `a1b2c3d4`
Initiated by: tech-lead
Next Step: 🚀 The Test and Deploy workflow will automatically trigger for the updated `develop` branch

[View Branch] [View Actions]

🤖 XWP Deploy Bot
```

### 11. Branch Reset Failed (reset-branch.yml)

```
🔴 ❌ Branch Reset Failed - Immediate Attention Required
❌ Branch Reset Failed

Operation: Reset `develop` to match `main`
Source Branch: `main`
Target Branch: `develop`
Initiated by: tech-lead
Status: ⚠️ Branch may be in an inconsistent state - manual intervention may be required

[View Logs]

🤖 XWP Deploy Bot
```

### 12. Production Release Completed (cleanup-release-branch.yml)

```
🟢 🎉 Production Release Completed & Cleaned Up
🎉 Production Release Successfully Deployed

Version: release-20240916-143022
Deployed by: tech-lead
PR: #42
Branch Cleanup: Release branch deleted
Actions Completed:
• Production deployment successful
• Release tag created: release-20240916-143022
• GitHub release published
• Release branch cleaned up

[View Production] [View Release] [View Deployment]

🤖 XWP Deploy Bot
```

### 13. Release Cleanup Failed (cleanup-release-branch.yml)

```
🔴 ❌ Production Release Cleanup Failed
❌ Release Cleanup Failed

Version: release-20240315-143022
PR: #42
Failed Step: Check the workflow logs to identify which cleanup step failed
Impact: Production deployment succeeded, but cleanup tasks may need manual completion
Manual Actions Required:
• Check if release branch needs manual deletion
• Verify GitHub release was published
• Confirm production tag exists

[View Logs] [View Production]

🤖 XWP Deploy Bot
```

## Footer

All notifications include an enhanced footer showing:
```
🤖 XWP Deploy Bot
```
(or the custom name configured in `GIT_USER_NAME` variable with robot emoji)

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
| **Reset Branch** | Manual action | Branch Reset Successful | Branch Reset Failed |
| **Create Production PR** | Manual action | Production PR Created | Production PR Failed |
| **Release Cleanup** | Auto after merge | Production Release Completed | Release Cleanup Failed |
