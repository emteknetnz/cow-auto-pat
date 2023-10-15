## cow-auto-pat

Use during `silverstripe/recipe-kitchen-sink` release to update `.cow.pat.json`
- fill in prior versions
- work out next minor version from GitHub API

### Setup

Add the following files to the root folder:
- `.cow.pat.json` - this is whatever cow spits out first time after release:plan, it'll be wrong
- `.prior.cow.pat.json` - copy paste the .cow.pat.json from confluence of the previous stable release

Create the file `.credentials`
```
token=abcdef123456
```

Copy the file `release.sample.txt` to `release.txt` and set `1` (yes) or `0` (no) for the modules you wish to release.
`U` (upgarde-only) moduels will never release.  `0` and `U` modules will use the latest availabe tag for the current major

There's some code within run.php to regenerate `release.sample.txt` if needed

### Usage
`php run.php`

Will create `output.json` - use this as your new `.cow.pat.json`

The correct pre-stable suffix (if any) will be taken from the version of `silverstripe/recipe-kitchen-sink` in the `.cow.pat.json` file.