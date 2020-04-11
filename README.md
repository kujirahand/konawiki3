# konawiki3

 - Wiki clone
 - Konawiki --- http://kujirahand.com/konawiki3/

## Konawiki3 is simple.

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

## 日本語構文のサポート

```
■大見出し

text text text text

●中見出し

text text text text

▲小見出し

text text text text

リスト

・item1
・item2
・item3
```

# Install Konawiki3

- 1. Install WebServer and PHP5 or PHP7
- 2. ``cp tmp-konawiki3.ini.php konawiki3.ini.php``

## (Option) Mark down support

- Mark down also can write.
- When you want to use Mark down, file name "xxx.md"

```
# install mark down component
$ cd kona3engine
$ composer install
```

## Git support

You can commit and push wiki diffs to your git repository.

```
# set your remote repository in data/
$ cd data
$ git remote add origin git@github.com:hoge/fuga.git

# run setup script
$ cd ../kona3engine
$ bash enable_git_support.sh
```
