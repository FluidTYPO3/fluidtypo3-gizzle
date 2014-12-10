FluidTYPO3 Gizzle
=================

[![Build Status](https://img.shields.io/jenkins/s/https/jenkins.fluidtypo3.org/fluidtypo3-gizzle.svg?style=flat-square)](https://travis-ci.org/FluidTYPO3/fluidtypo3-gizzle) [![Coverage Status](https://img.shields.io/coveralls/FluidTYPO3/fluidtypo3-gizzle/master.svg?style=flat-square)](https://coveralls.io/r/FluidTYPO3/fluidtypo3-gizzle)

> Official Fluid Powered TYPO3 Gizzle implementation

What is this?
-------------

Gizzle is a light-weight GitHub webhook listener written by [Claus Due](https://namelesscoder.net). In as few words as possible, _Gizzle listens to requests from GitHub when activity occurs in your repositories_. This package is an implementation of Gizzle in a composer project - and contains a few key plugins which provide integration with commits, pull requests and the TYPO3 extension repository.

The following plugins are included:

* `FormalitiesPlugin` which will analyse commits; validating the coding style of `.php` files using CodeSniffer and the FluidTYPO3 rule set, and validating that commit messages conform to the TYPO3 standards regarding prefix, casing etc.
* `SiteDeployPlugin` which, unsurprisingly, deploys repositories (in the case of Fluid Powered TYPO3 extensions, each of the three main branches are deployed to individual sites and for master, to fluidtypo3.org itself).

In addition the package utilises:

* `ExtensionReleasePlugin` from the [gizzle-typo3-plugins](https://github.com/NamelessCoder/gizzle-typo3-plugins) set of plugins which we utilise to trigger automatic uploads to the TYPO3 extension repository when a new git tag is created in a repository that is a TYPO3 extension.

Together, these plugins form a near-realtime feedback system for pull requests (which even comments on each individual line if CGL was violated with a note about the nature of the violation), which sets a status indicator on pull requests' HEAD commit which GitHub will display in the pull request on GitHub; as well as an automatic release system which behaves like Packagist in that it uses tags to detect new versions - but then releases to TER instead.

Sister implementation
---------------------

The `fluidtypo3-gizzle` implementation of Gizzle has a sister project, `fluidtypo3-development`, aimed at assisting local development in repositories that are TYPO3 extensions and which follow Fluid Powered TYPO3 coding style and rules. `fluidtypo3-development` carries with it a range of command line utilities that either validate or manipulate the code in the repository; for example updating the version number consistently in both `composer.json` and `ext_emconf.php` files and manually uploading new versions to TER (an action which `fluidtypo3-gizzle` automates). It will also install hooks for the git command which validate commit messages and runs coding style validation and unit tests **before** files are allowed to be committed. In fact, `fluidtypo3-gizzle` utilises `fluidtypo3-development` for some features and for the continuous integration that is used in the official repository.

So where `fluidtypo3-gizzle` can validate the stuff that goes on via GitHub, `fluidtypo3-development` can validate it **before** it reaches GitHub.

Using both in combination should ensure a minimum of human involvement in reviewing pull requests and a minimum of frustration for contributors because with `fluidtypo3-development` they'll be warned in advance if they're about to commit incorrect code - and they'll be warned even if they use GitHub's web interface to create the commits that become the pull request (but unfortunately, not until it actually becomes a pull request).

Do-it-yourself instructions
---------------------------

The following instructions teach you how to fork, install and if necessary adapt `fluidtypo3-gizzle` to do the exact same thing for your own repositories.

### Installing files and HTTPD vhost

First of all, fork the repository so that you can commit and push any changes you need to make and preserve them in your own repository. Then, clone your forked repository to your web server:

```bash
git clone https://github.com/YOURNAME/fluidtypo3-gizzle.git
```

Then switch into this directory and run Composer's install:

```bash
cd fluidtypo3-gizzle
composer install --no-dev
```

If you intend to develop, we suggest removing the `--no-dev` to get phpunit and other utilities installed. If installed without `--no-dev` this package will add a new command that you can call once and enable the local development assistant described above:

```bash
./vendor/bin/make
```

Calling this command *once* is enough - after that, it will automatically be called before each commit you make, triggering code style validation and unit testing.

Lastly, create a virtual host that points to the `web` folder inside the project (find the appropriate instructions for your HTTPD of choice). Optionally you can set the Apache `DirectoryIndex` or corresponding option in another HTTPD to serve the `github-webhook.php` file as default, which lets you shorten your URLs that you use for GitHub web hooks.

### Preparing credentials

Gizzle requires at least a so-called `secret` file which contains a secret you use when installing your web hook URLs in GitHub. You can choose this secret now and remember it when you create each web hook. Create the file in your project root directory:

```bash
echo "mysecretphrase" > .secret
```

In all web hooks you add on GitHub a dedicated input field is there for your `secret` - enter the same `secret` you placed in this file, in all web hooks you create (and which are received by this host, of course).

**DON'T commit these files to your repository!** Add them to .gitignore instead.

Depending on which of the plugins you expect to use you will also require one or both of the following:

#### GitHub personal access token

Personal Access Tokens can be generated via your account settings on GitHub and once generated, allows applications such as Gizzle to use GitHub as if it were using your account. The Personal Access Token is required for `fluidtypo3-gizzle` to be able to use the `FormalitiesPlugin` because it can comment on files in commits; and it is required for the updating of status on pull requests.

When creating your Personal Access Token you decide which privileges it should have. The absolute minimum requirements for use with `fluidtypo3-gizzle` is the "public repos" and "comment" permissions; plus whichever permissions you plan to use if you plan to create your own Gizzle plugins.

If you decide to use a Personal Access Token, add it to your project root directory once you've created it and given it privileges:

```bash
echo "1234567890abcdef1234567890abcdef" > .token
```

Protect your Personal Access Token with the same care you would your GitHub login and password. **DON'T commit these files to your repository!**

#### TYPO3 credentials

In order to enable automatic uploads of repositories to the TYPO3 extension repository when new git tags are made, `fluidtypo3-gizzle` requires a `.typo3credentials` file which contains both username and password in clear text:

```bash
echo "username:password" > .typo3credentials
```

Protect your credentials. Make sure only people you trust have access to it! **DON'T commit these files to your repository!**

### Installing web hooks in repositories

Once you have prepared the credentials, Gizzle will be able to validate the request coming from GitHub and ensure you won't receive requests from any other source.

The first repository to which you should add a hook is in your fork of `fluidtypo3-gizzle`. The web hook you add here should be running the `SelfUpdate.yml` settings file which basically 1) pulls changes from the remote tracked branch and 2) runs composer install, in the project itself. This allows you to push changes to your fork and have them deployed immediately. To add this web hook, edit the settings of your repository and in the "Web services and hooks" category, choose to create a new web hook. In the URL field, enter your virtual host's public URL and reference a settings file:

```plain
http://mydomain.com/github-webhook.php?settings=settings/SelfUpdate.yml
```

...and select to have it send you "just the push event".

You can test the hook to confirm that at least your `secret` file is working but test requests will not trigger anything.

Then, in each of the repositories that you wish to mark for automatic validation of coding style using ours or your own rule set, add another web hook and this time, reference the `PullRequest.yml` settings file:

```plain
http://mydomain.com/github-webhook.php?settings=settings/PullRequest.yml
```

But for this hook make sure it only receives the "pull request" event! Once added, this web hook will listen for new pull requests and changes to existing pull requests and when those occur, catch the files and commits that are being touched and validate them according to configured rules.

The final manifest that is provided by `fluitypo3-gizzle` is the `ExtensionRelease.yml` settings file. Referencing this file allows you to capture only the events that tag new releases:

```plain
http://mydomain.com/github-webhook.php?settings=settings/ExtensionRelease.yml
```

And for this hook, make sure it receives "just the push event". This means that the listener receives an event whenever any type of commit - not just tags - are created, but the plugin filters these out but only recognising "refs" which begin with the special `heads/tags/` prefix.

On a final note: you can dispatch more than one settings manifest in each web hook:


```plain
http://mydomain.com/github-webhook.php?settings[]=settings/Manifest1.yml&settings[]=settings/Manifest2.yml
```

Note that the `settings` parameter is turned into an array; any number of manifests can be added here (as long as the resulting URL is still of valid length). However: be careful when combining settings manifests this way - make sure that all the plugins which are involved actually match the event type or you will quickly find yourself with a bunch of events added and many redundant events, with many HTTP unnecessary requests to your web hook listener as a result.


Settings manifests and plugin references
----------------------------------------

Each of the manifest `.yml` files inside the `settings` directory consist of one ore more configurations of plugins that should be executed, along with parameters for the execution. To learn more about how such manifests can be created take a look in the [official Gizzle reference about configuration](https://github.com/NamelessCoder/gizzle#configuring-plugins).

The parameters supported by each of the plugins are documented in the repositories to which the plugin belongs; with the exception of the `SiteDeploy` and `Formalities` plugins which are tailored to Fluid Powered TYPO3 extensions _and thus support only the parameters that are already defined in the settings file, nothing more, nothing less_.

The plugins and libraries involved are located in the following repositories:

* https://github.com/NamelessCoder/gizzle-git-plugins which is used to trigger standard git commands through a manifest.
* https://github.com/NamelessCoder/gizzle-typo3-plugins which is used for the extension release logic.
* https://github.com/NamelessCoder/typo3-repository-client which carries the actual extension upload logic - and has CLI commands you can use for the same purpose.
* https://github.com/FluidTYPO3/fluidtypo3-gizzle which is the upstream of this project and any forks hereof.
