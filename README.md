# KonaWiki3

- KonaWiki3 is Wiki clone.
- [URL] http://kujirahand.com/konawiki3/
- [日本語](README-ja.md)

# Goals of KonaWiki3

KonaWiki is a simple Wiki designed specifically for writing manuscripts, creating manuals, and sharing information.

The data in the Wiki is just plain text files, which allows for detailed differences to be tracked in conjunction with Git.
Developers have been using KonaWiki3 as a substitute for text editors for many years. Let's accomplish great tasks using the small and simple KonaWiki.

Additionally, through the configuration options, it is possible to save in Markdown as well as KonaWiki notation. You can use your preferred notation for markup.

## KonaWiki3 is simple.

It is very simple PHP wiki engine.

```
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

{{{#code(js)
console.log('hello');
}}}
```

# How to install Konawiki3

[Use Git command]

- 1. Install WebServer and PHP
- 2. Download
  - `git clone --recursive https://github.com/kujirahand/konawiki3.git`
  - OR `git clone https://github.com/kujirahand/konawiki3.git` AND `git submodule update --init --recursive`

[Use Uploader]

- 1. Download Konawiki3 from [releases](https://github.com/kujirahand/konawiki3/releases)
- 2. Download Template Engine from [php_fw_simple](https://github.com/kujirahand/php_fw_simple/releases)

Please set directories like this.

```
- index.php
- <data>
- <cache>
- <private>
+ <kona3engine>
    + <fw_simple>
      - README.md
      - index.lib.php
      ...
    - <action>
    - <template>
    - <lang>
    ...
```

And put `data/.htaccess`.

```
# access limitation
<Files *.txt>
Order deny,allow
Deny from all
</Files>
<Files *.md>
Order deny,allow
Deny from all
</Files>
```

## Install Library (Option)

When you want to use Git, execute shell commands.

```sh
cd kona3engine
composer install
```

## Output PDF (Option)

- 1. Access config page, and set PDF-output TRUE
- 2. If Mojibake then put fonts in ./vendor/fonts(.ttf)
- [PDF output manual](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Git support (Option)

You can commit and push wiki diffs to your git repository.

```sh
# set your remote repository in `/data` dir
cd data
git remote add origin git@github.com:hoge/fuga.git
```

And set git_enabled to true at the config.

## Config page

First login by Admin User, and access config page.

```
[URI] index.php?go&editConf
```

## Plugin list

```
[URI] index.php?FrontPage&plugin&name=pluginlist
```
