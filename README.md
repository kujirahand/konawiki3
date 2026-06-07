# Konawiki3

Konawiki3 is a simple Wiki designed specifically for writing manuscripts, creating manuals, and sharing information.

- Konawiki3 is Wiki clone.
- [URL] http://kujirahand.com/konawiki3/
- [日本語のREADME](README-ja.md)

## Goals of Konawiki3

The data in the Wiki is **just plain text** files, which allows for detailed differences to be tracked in conjunction with Git.
You can use Konawiki3 instead of a text editor. Let's accomplish great tasks using the small and simple Konawiki.

Additionally, through the configuration options, it is possible to save in `Markdown` as well as `KonaNotation`. You can use your preferred notation for markup.

## Text-based Wiki

You can write pages in plain text files that support both `KonaNotation` and `Markdown`. This allows you to easily track changes using Git and manage your content efficiently.

And you can switch easily between `KonaNotation` and `Markdown` for your pages. This flexibility allows you to choose the markup language that best suits your needs and preferences. In addition to configuration settings, you can also switch by file extension. Files named `plain.txt` are treated as `KonaNotation`, and `plain.md` files are treated as Markdown automatically.

### KonaNotation example (*.txt)

Konawiki notation is a simple markup language. Here are some examples:

```text
* header

text text text text

** header2

table:

| table | test
| aaa | bbb

list:

- item1
- item2
- item3

code:

{{{#code
console.log('hello');
}}}


plugins:

#comment
```

### Japanese Wiki Notation in KonaNotation (*.txt)

We also support Japanese-specific notation.

```text
■Large Heading

Text Text Text Text

●Medium Heading

Text Text Text Text

▲Small Heading

Text Text Text Text

List

・item1
・item2
・item3
```

### Markdown example (*.md)

Markdown is a widely used markup language. Here are some examples:

```markdown
# header

text text text text

## header2

table:

| table | test |
| --- | --- |
| aaa | bbb |

list:

- item1
- item2

```js
console.log('hello');
```

plugins:

!!comment
```

### AI Writing Assistance Features

We are implementing AI writing assistance features. These include summarizing texts, proofreading, rephrasing sentences, and automatically continuing writing, among others.
By obtaining a [ChatGPT API key](https://platform.openai.com/api-keys) and specifying it in the Konawiki settings, users can access AI support functions.

### Tag System for Page Organization

A file-based tag system is built in. By adding `#tag(TagName)` to a page, you can organize pages by category.
Use tags to classify pages, and display a list of pages with a specific tag using `#tags(TagName)`.

For more details, see the [Tag System Documentation](docs/TAG_SYSTEM.md).

## How to install Konawiki3

### Use Git command

1. Install WebServer and PHP
2. Clone repository

```sh
git clone https://github.com/kujirahand/konawiki3.git
```

### Use Uploader to Hosting server

1. Download Konawiki3 zip file from [releases](https://github.com/kujirahand/konawiki3/releases)
2. Unzip
3. Change permissions

```sh
chmod 766 data
chmod 766 cache
chmod 766 private
```

For more details, see also [installation guide](https://kujirahand.com/konawiki3/index.php?install).

### Use Docker (Local Environment)

Run the following command in your terminal to start. This runs Konawiki3 using PHP's built-in local server.

```sh
docker-compose up
```

## Install Library (Option)

When you want to use the Git save feature, install it using composer.

```sh
cd kona3engine
composer install
```

* Note: Depending on your hosting server, Git operations from PHP scripts may be restricted. In that case, commit via SSH or set up a cron job to commit regularly as follows:

```sh
cd konawiki_path
git pull
git add ./data
git commit -a
git push
```

## Output PDF (Option)

1. Access the config page, and set PDF-output to TRUE.
2. If mojibake (character corruption) occurs, place your font files (`.ttf`) in `./vendor/fonts`. - [PDF output manual](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Git support (Option)

You can commit and push wiki diffs to your git repository.

```sh
# set your remote repository in `/data` dir
cd data
git remote add origin git@github.com:hoge/fuga.git
```

And set `git_enabled` to `true` in the configuration.

## Config page

First login by Admin User, and access config page.

```text
[URI] index.php?go&editConf
```

## Plugin list

```text
[URI] index.php?FrontPage&plugin&name=pluginlist
```

## For Development

We use the `just` task runner.
To run tests, execute `just test`.
