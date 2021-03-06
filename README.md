# Iterate through git commits

**Work in progress**

Configuration example (`conf.yml`):

```yaml
repository: https://github.com/mnapoli/silly
tasks:
    'Commit message': "git log -1 --pretty=%B | head -n 1"
    'Commit author': "git log -1 --pretty=%an"
    'Number of files': "find . -type f | wc -l | xargs"
    'Number of directories': "find . -type d | wc -l | xargs"
```

To run the application:

```
php app.php run
```

The git repository will be cloned in a `repository` folder, and tasks will be run against each commit.

The output will look like this:

```
Commit,Date,Number of files,Number of directories
d612a29fae3b0f625b9be819802e93214d4eecd9,2016-08-31T12:55:38+02:00,61,28
497f22a27896d146a35660f452eba24d3a14db3f,2016-08-31T12:53:01+02:00,61,28
fc0646f236e6bb0a10b14a67424f932f28eb1062,2016-08-26T19:29:40+02:00,62,28
221528e63d7aac3aa247dfde191b5f6c380cbb7e,2016-08-25T01:28:55+02:00,62,28
...
```

The output is formatted as CSV, you can write that to a file:

```
php app.php run > results.csv
```

You can then import that into a database or open it up with Excel or whatever.

You can also output the result as SQL queries:

```
php app.php run --format=sql | mysql -u dbuser -p mytable
```
