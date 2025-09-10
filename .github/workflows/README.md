# GitHub Actions Workflows

## Docker Image Management

### Quick Start

For your first PR with Docker changes:
```bash
# After pushing your PR, you'll see CI fail with "manifest unknown"
# This is expected! Now publish the images:
git tag -f docker-image-build
git push -f origin docker-image-build

# Wait for docker-images workflow to complete, then re-run failed CI jobs
```

### How It Works

Docker images are built automatically when Docker files change, but only published to the registry when explicitly requested via a Git tag. This ensures version immutability and prevents accidental overwrites.

#### Workflow Triggers

The `docker-images.yml` workflow runs when:
- Docker-related files change (Dockerfile, docker-compose.yml)
- The `docker-image-build` tag is created/updated
- Manually triggered from GitHub Actions UI

#### Publishing Docker Images

Images are **only** published to GitHub Container Registry when:
1. You create/update the `docker-image-build` tag
2. You manually trigger the workflow from GitHub Actions UI

When triggered by file changes alone, images are built but NOT published.

#### Creating a Docker Release

When you need to publish new Docker images:

1. **Update the version** in `docker-compose.yml` (if needed):
   ```yaml
   image: ghcr.io/xwp/vip-site-template--wordpress:2.3.0  # ← Bump this
   ```

2. **Commit and push** your changes:
   ```bash
   git add docker-compose.yml local/docker/
   git commit -m "Update WordPress container to Node.js 20"
   git push
   ```

3. **Create/update the docker-image-build tag** to publish:
   ```bash
   git tag -f docker-image-build
   git push -f origin docker-image-build
   ```

4. The `docker-images.yml` workflow will:
   - Build the images
   - Push them to `ghcr.io` with the version from docker-compose.yml
   - Make them available for all future CI runs

Note: The `-f` flag allows you to move the tag to the latest commit when needed.

### Test and Deploy Workflow

The `test-deploy.yml` workflow runs on every push and:
1. **Pulls** the Docker images from the registry
2. Runs tests and deployments

**Important:** The workflow will fail if Docker images haven't been published yet. This is intentional to ensure all CI runs use the same published images.

### Version Management Best Practices

#### When to Create a New Version

Create a new Docker image version when you:
- Update the Dockerfile (new tools, dependencies, configurations)
- Change base images (e.g., `wordpress:php8.2-apache` → `wordpress:php8.3-apache`)
- Fix bugs in the container setup
- Update Node.js, PHP, or other runtime versions

#### When NOT to Create a New Version

Don't create a new version for:
- PHP/JavaScript code changes
- Composer/npm dependency updates (handled at runtime)
- Theme or plugin updates

### Example Workflow

1. **Initial setup** (first PR with Docker changes):
   ```bash
   # After pushing your PR, publish the Docker images:
   git tag -f docker-image-build
   git push -f origin docker-image-build
   # Re-run failed CI jobs - they will now pass
   ```

2. **Regular development**:
   - Push code changes normally
   - CI pulls existing Docker images from registry
   - Tests run fast without rebuilding images

3. **When Dockerfile changes**:
   ```bash
   # Edit Dockerfile and bump version in docker-compose.yml
   vim local/docker/wordpress/Dockerfile
   vim docker-compose.yml  # Change 2.2.0 → 2.3.0

   # Commit and push
   git add .
   git commit -m "feat: Update Docker images with Node.js 20"
   git push

   # Publish the new images
   git tag -f docker-image-build
   git push -f origin docker-image-build
   ```

The single `docker-image-build` tag can be reused - it always publishes whatever version is in docker-compose.yml.

### Manual Trigger

You can also manually trigger the Docker image build:
1. Go to Actions tab in GitHub
2. Select "Build and Publish Docker Images"
3. Click "Run workflow"
4. Select the branch and click "Run workflow"

### Benefits

- **Immutable versions**: Once published, version 2.2.0 never changes
- **Explicit control**: You decide when to publish new images via the `docker-image-build` tag
- **Efficient CI**: No unnecessary rebuilds or publishes
- **Validation**: Docker changes are built automatically to catch errors early
- **Simple workflow**: One reusable tag for all publishes

### Troubleshooting

**Q: CI fails with "manifest unknown" error**
A: Docker images haven't been published yet. Create the tag:
```bash
git tag -f docker-image-build
git push -f origin docker-image-build
```

**Q: I updated the Dockerfile but CI is using old images**
A: 
1. Bump the version in docker-compose.yml (e.g., 2.2.0 → 2.3.0)
2. Push your changes
3. Update the tag to publish: `git tag -f docker-image-build && git push -f origin docker-image-build`

**Q: Can I overwrite an existing image version?**
A: Yes, by using the same version and pushing the tag again, but this is discouraged. Better to bump the version for traceability.

**Q: How do I know which Docker image version is being used?**
A: Check the `docker-compose.yml` file for the current image tags.

**Q: The docker-images workflow ran but images weren't published**
A: This is expected when triggered by file changes alone. Only the `docker-image-build` tag or manual trigger publishes images.
