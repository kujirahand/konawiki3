# Konawiki3

Konawiki3 is a simple Wiki designed specifically for writing manuscripts, creating manuals, and sharing information.

- Konawiki3 is a Wiki clone.
- [URL] http://kujirahand.com/konawiki3/
- [Japanese README](README-ja.md)

## Goals of Konawiki3

The data in the Wiki is stored as **plain text** files, so you can track detailed changes with Git.
You can use Konawiki3 as an alternative to a text editor.
Manage your documents with the small and simple Konawiki!
The main developer has used Konawiki to write dozens of technical books.

You can choose either `Markdown` or Konawiki's original markup language, [KonaNotation](docs/kona_notation.md), in the configuration and use whichever markup style you prefer.
Konawiki3 also includes a [plugin system](kona3engine/plugins/README.md), making it easy to extend its functionality.

## Text-based Wiki

Pages can be written as plain text files that support both `KonaNotation` and `Markdown`.
This makes it easy to track changes with Git and manage content efficiently.

You can also switch each page between `KonaNotation` and `Markdown`.
This flexibility lets you choose the markup language that best fits your needs and preferences.
In addition to changing the setting, you can switch markup modes by file extension.

A file named `plain.txt` is automatically treated as `KonaNotation`, while a file named `plain.md` is automatically processed as `Markdown`.

## Configuration Through the Admin Page

Konawiki3 lets you configure many settings through the admin page. You do not need to edit the configuration file directly.
These settings include site appearance, user management, and plugin enablement.
After logging in as an administrator, open the configuration page to customize how the site works.

## AI Writing Assistance

Konawiki3 includes AI writing assistance features. These include text summarization, proofreading, sentence rephrasing, and automatic continuation of writing.
You can access the AI assistance features by obtaining a [ChatGPT API key](https://platform.openai.com/api-keys) and setting it in Konawiki.

## Tag System for Page Organization

Konawiki3 includes a file-based tag system. Add `#tag(TagName)` to a page to organize pages by category.
Use tags to classify pages, and use `#tags(TagName)` to display a list of pages with a specific tag.

For details, see the [Tag System documentation](docs/TAG_SYSTEM.md).

## How to Install Konawiki3

You can easily install Konawiki3 if you have a web server that can run PHP.

### Installing with Git

1. Install a web server and PHP.
2. Run the following commands to clone the repository and change permissions.
3. Open `index.php` in your browser, create an administrator user, and configure the site.

```sh
# clone the repository
git clone https://github.com/kujirahand/konawiki3.git
# chmod the data, cache, and private directories
chmod 766 data
chmod 766 cache
chmod 766 private
```

### Installing on a Hosting Server with an Uploader

1. Download the Konawiki3 zip file from [releases](https://github.com/kujirahand/konawiki3/releases).
2. Unzip it.
3. Run the following commands to change permissions.
4. Open `index.php` in your browser, create an administrator user, and configure the site.

```sh
chmod 766 data
chmod 766 cache
chmod 766 private
```

For details, see the [installation guide](https://kujirahand.com/konawiki3/index.php?install).

### Using Docker (Local Environment)

Run the following command in your terminal to start Konawiki3. This runs Konawiki3 using PHP's built-in local server.

```sh
docker-compose up
```

## Installing Libraries (Optional)

If you want to use features such as Git saving or simple PDF generation, install the libraries with Composer.

```sh
cd kona3engine
composer install
```

Depending on your hosting server, Git operations from PHP scripts may be restricted.
In that case, commit via SSH or use cron to commit regularly as follows:

```sh
cd konawiki_path
git pull
git add ./data
git commit -a
git push
```

## Accessing the Configuration Page

First, log in as an administrator and open the configuration page.

```text
[URI] index.php?go&editConf
```

## Plugin List

Open the plugin list page in your Konawiki3 installation.

```text
[URI] index.php?FrontPage&plugin&name=pluginlist
```

## PDF Output (Optional)

1. Open the configuration page and set PDF output to TRUE.
2. If mojibake (garbled characters) occurs, place font files (`.ttf`) in `./vendor/fonts`. - [PDF output manual](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Git Support (Optional)

You can commit Wiki diffs and push them to a Git repository.

```sh
# set a remote repository in the data directory
cd data
git remote add origin git@github.com:hoge/fuga.git
```

Then set `git_enabled` to `true` in the configuration.

## For Developers

Konawiki3 uses the `just` task runner.
To run tests, execute `just test`.
