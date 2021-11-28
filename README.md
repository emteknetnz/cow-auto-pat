## cow-auto-pat

Automatically fill in prior versions and fetch new versions from GitHub API

Add the following files to the root folder
.cow.pat.json
.prior.cow.path.json

Will create output.json

Add the following to .env
GITHUB_USER="abc"
GITHUB_TOKEN="def"

vendor/bin/sake dev/tasks/UpdateTask
