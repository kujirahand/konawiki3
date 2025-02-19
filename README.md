# Konawiki3

Konawiki is a simple Wiki designed specifically for writing manuscripts, creating manuals, and sharing information.

- Konawiki3 is Wiki clone.
- [URL] http://kujirahand.com/konawiki3/
- [日本語のREADME](README-ja.md)

# Goals of Konawiki3

The data in the Wiki is just plain text files, which allows for detailed differences to be tracked in conjunction with Git.
You can use Konawiki3 instead of a text editor. Let's accomplish great tasks using the small and simple Konawiki.

Additionally, through the configuration options, it is possible to save in `Markdown` as well as `Konawiki notation`. You can use your preferred notation for markup.

## Konawiki3 is simple.

It is very simple wiki engine.

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

### AI Writing Assistance Features

We are implementing AI writing assistance features. These include summarizing texts, proofreading, rephrasing sentences, and automatically continuing writing, among others.
By obtaining a [ChatGPT API key](https://platform.openai.com/api-keys) and specifying it in the Konawiki settings, users can access AI support functions.

# How to install Konawiki3

## Use Git command

1. Install WebServer and PHP
2. Clone repository

```sh
git clone https://github.com/kujirahand/konawiki3.git
```

## Use Uploader to Hosting server

1. Download Konawiki3 zip file from [releases](https://github.com/kujirahand/konawiki3/releases)
2. Unzip
3. Change permissions

```sh
chmod 766 data
chmod 766 cache
chmod 766 private
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

## Docker

```
docker-compose up
```


