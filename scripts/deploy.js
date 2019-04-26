const exec = require('child_process').execSync;
const parseArgs = require('minimist')
const path = require('path')
const fs = require('fs-extra')
const git = require('simple-git')
const ignore = require('ignore')

// Parse the command-line arguments.
const argv = parseArgs(process.argv.slice(2))
const srcBranch = argv._[1]
const upstreamRepo = argv._[0]

const rootDir = path.join(__dirname, '..')

const config = {
  src: {
    repo: rootDir,
    releaseDir: path.join(rootDir, 'deploy/src/public'),
    branch: srcBranch,
    dir: path.join(rootDir, 'deploy/src'),
  },
  dist: {
    repo: upstreamRepo,
    repoDir: path.join(rootDir, 'deploy/dist-src'),
    branch: `deploy/${srcBranch}-${Math.round(new Date().getTime()/1000)}`,
    dir: path.join(rootDir, 'deploy/dist'),
  },
  build: 'npm install && npm run release',
  distignore: path.join(rootDir, 'public/.distignore')
}

// Always start with fresh source and release directories.
fs.emptyDirSync(config.src.dir)
fs.emptyDirSync(config.dist.dir)
fs.emptyDirSync(config.dist.repoDir)

// Checkout a fresh copy of the source branch in a new directory.
fs.copySync(
  path.join(config.src.repo, '.git'),
  path.join(config.src.dir, '.git')
)

// Checkout a fresh source repository.
git(config.src.dir)
  .reset('hard')
  .checkout(config.src.branch)

// Checkout a fresh release repository.
git(config.dist.repoDir)
  .clone(config.dist.repo, config.dist.repoDir)
  .reset('hard')
  .checkoutBranch(config.dist.branch, config.src.branch)

// Run the build.
exec(config.build, {
  cwd: config.src.dir
})

// Copy the build to the release directory.
fs.copySync(
  config.src.releaseDir,
  config.dist.dir,
  {
    filter: ignore().add(fs.readFileSync(config.distignore).toString()).createFilter()
  }
)

// Now make the release directory a release repository.
fs.copySync(
  path.join(config.dist.repoDir, '.git'),
  path.join(config.dist.dir, '.git')
)
