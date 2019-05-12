const exec = require('child_process').execSync;
const parseArgs = require('minimist')
const path = require('path')
const fs = require('fs-extra')
const git = require('simple-git/promise')
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

const log = console.log

const gitOutputHandler = (command, stdout, stderr) => {
  stdout.pipe(process.stdout);
  stderr.pipe(process.stderr);
}

const gitSrc = git(config.src.dir).outputHandler(gitOutputHandler)
const gitDist = git(config.dist.repoDir).outputHandler(gitOutputHandler)

// Checkout a fresh source repository.
gitSrc.reset('hard')
  .then(() => {
    log(`Checking out ${config.src.branch} branch to ${config.src.dir}.`)
    return gitSrc.checkout(config.src.branch)
  })
  .then(() => {
    log(`Cloning ${config.dist.repo} to ${config.dist.repoDir}.`)
    return gitDist.clone(config.dist.repo, config.dist.repoDir)
  })
  .then(() => gitDist.reset('hard'))
  .then(() => {
    log(`Checking out ${config.dist.branch} branch in ${config.dist.repoDir}.`)
    return gitDist.checkoutBranch(config.dist.branch, config.src.branch)
  })
  .then(() => {
    log(`Building release in ${config.src.dir}.`)
    exec(config.build, {
      cwd: config.src.dir
    })

    log(`Copying release from ${config.src.releaseDir} to ${config.dist.dir}.`)
    return fs.copy(
      path.relative(rootDir, config.src.releaseDir),
      path.relative(rootDir, config.dist.dir),
      {
        filter: ignore().add(fs.readFileSync(config.distignore).toString()).createFilter()
      }
    )
    .then(() => {
      log(`Making the release directory ${config.dist.dir} an upstream Git repository.`)
      return fs.copy(
        path.join(config.dist.repoDir, '.git'),
        path.join(config.dist.dir, '.git')
      )
    })
    .then(() => {
      const gitRelease = git(config.dist.dir).outputHandler(gitOutputHandler)

      return gitRelease.raw(['add', '--all'])
        .then(gitRelease.commit(`Deploy ${config.src.branch}`))
        .then(gitRelease.push('origin', config.dist.branch))
    })
  })
  .then(() => {
    // TODO: Print a URL for opening the pull request.
    log(`Done! Now open a pull request from ${config.dist.branch} to ${srcBranch} on ${upstreamRepo} to submit the changes for code review.`)
  })
  .catch((err) => console.error(err))
