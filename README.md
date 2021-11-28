## cow-auto-pat

Use during `silverstripe/recipe-kitchen-sink` release to update `.cow.pat.json`
- fill in prior versions
- work out next mionr version from GitHub API

### Setup

Add the following files to the root folder:
- `.cow.pat.json`
- `.prior.cow.path.json`

Create the file `.credentials`
```
user=my_github_username
token=abcdef123456
```

### Usage
`php run.php`

Will create `output.json` - use this as your new `.cow.pat.json`

### Option tag suffix

`php run.php beta1`

`php run.php rc1`



