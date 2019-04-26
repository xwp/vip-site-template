const parseArgs = require('minimist')
const path = require('path')
const fs = require('fs-extra')
const git = require('simple-git')

const rootDir = path.join(__dirname, '..')

const argv = parseArgs(process.argv.slice(2))
const srcBranch = argv._[1]
const upstreamRepo = argv._[0]

const config = {
  src: {
    from: rootDir,
    branch: srcBranch,
    dir: path.join(rootDir, 'deploy/src'),
  },
  dist: {
    repo: upstreamRepo,
    branch: `deploy/${srcBranch}-${Math.round(new Date().getTime()/1000)}`,
    dir: path.join(rootDir, 'deploy/dist'),
  },
  build: "npm install && npm run release",
  distignore: path.join(rootDir, 'public/.distignore'),
}

async function getHeadRevision(repoPath) {
  return await git(config.src.dir).revparse();
}

// Checkout a fresh copy of the source branch in a new directory.
fs.emptyDirSync(config.src.dir)
fs.copySync(path.join(config.src.from, '.git'), path.join(config.src.dir, '.git'))
git(config.src.dir).checkout(config.src.branch).reset('hard')

// Checkout a fresh release repository.
fs.emptyDirSync(config.dist.dir)
git().clone(config.dist.repo, config.dist.dir).checkoutBranch(config.dist.branch, config.src.branch)

